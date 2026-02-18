<?php

namespace App\Controllers;

use App\Models\BingoRoomModel;
use CodeIgniter\HTTP\ResponseInterface;

class BingoController extends BaseController
{
    private BingoRoomModel $roomModel;

    public function __construct()
    {
        $this->roomModel = new BingoRoomModel();
    }

    public function index(): string
    {
        return view('bingo');
    }

    public function createRoom(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $name = trim((string)($payload['name'] ?? ''));
        $board = $payload['board'] ?? null;
        $boardSize = $this->normalizeBoardSize($payload['boardSize'] ?? 5);

        if ($roomCode === '' || $name === '') {
            return $this->fail('Room code and name are required.', 400);
        }

        if ($boardSize === null) {
            return $this->fail('Board size must be 5, 7, or 9.', 400);
        }

        if ($this->readRoom($roomCode) !== null) {
            return $this->fail('Room already exists.', 409);
        }

        $playerId = $this->newPlayerId();
        $normalizedBoard = $this->normalizeBoard($board, $boardSize);
        $player = [
            'id' => $playerId,
            'name' => $name,
            'board' => $normalizedBoard,
            'lines' => 0,
            'ready' => $normalizedBoard !== null,
        ];

        $room = [
            'roomCode' => $roomCode,
            'createdAt' => time(),
            'boardSize' => $boardSize,
            'players' => [$player],
            'calledNumbers' => [],
            'turnIndex' => 0,
            'startIndex' => 0,
            'winnerId' => null,
            'lastCall' => null,
        ];

        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'playerId' => $playerId,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function joinRoom(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $name = trim((string)($payload['name'] ?? ''));
        $board = $payload['board'] ?? null;
        $boardSize = $this->normalizeBoardSize($payload['boardSize'] ?? 0);

        if ($roomCode === '' || $name === '') {
            return $this->fail('Room code and name are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $roomSize = (int)($room['boardSize'] ?? 5);
        if ($boardSize !== null && $boardSize !== $roomSize) {
            return $this->fail('Board size does not match the room.', 409);
        }

        if (!empty($room['calledNumbers'])) {
            return $this->fail('Game already started.', 409);
        }

        $playerId = $this->newPlayerId();
        $normalizedBoard = $this->normalizeBoard($board, $roomSize);
        $player = [
            'id' => $playerId,
            'name' => $name,
            'board' => $normalizedBoard,
            'lines' => 0,
            'ready' => $normalizedBoard !== null,
        ];

        $room['players'][] = $player;
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'playerId' => $playerId,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function updateBoard(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $playerId = (string)($payload['playerId'] ?? '');
        $board = $payload['board'] ?? null;

        if ($roomCode === '' || $playerId === '') {
            return $this->fail('Room code and player ID are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $roomSize = (int)($room['boardSize'] ?? 5);
        $normalizedBoard = $this->normalizeBoard($board, $roomSize);
        if ($normalizedBoard === null) {
            return $this->fail('Invalid board.', 400);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        if (!empty($room['calledNumbers'])) {
            return $this->fail('Game already started.', 409);
        }

        $room['players'][$playerIndex]['board'] = $normalizedBoard;
        $room['players'][$playerIndex]['ready'] = true;
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function getState(): ResponseInterface
    {
        $roomCode = $this->normalizeRoomCode($this->request->getGet('roomCode') ?? '');
        $playerId = (string)($this->request->getGet('playerId') ?? '');

        if ($roomCode === '') {
            return $this->fail('Room code is required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function callNumber(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $playerId = (string)($payload['playerId'] ?? '');
        $number = (int)($payload['number'] ?? 0);

        if ($roomCode === '' || $playerId === '' || $number <= 0) {
            return $this->fail('Room code, player ID, and number are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        if ($room['winnerId'] !== null) {
            return $this->fail('Game is over.', 409);
        }

        if (!$this->allPlayersReady($room)) {
            return $this->fail('Waiting for all players to be ready.', 409);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        if ($playerIndex !== (int)$room['turnIndex']) {
            return $this->fail('Not your turn.', 409);
        }

        $maxNumber = $this->maxNumberForRoom($room);
        if ($number < 1 || $number > $maxNumber) {
            return $this->fail('Number must be within the board range.', 400);
        }

        if (in_array($number, $room['calledNumbers'], true)) {
            return $this->fail('Number already called.', 409);
        }

        $room['calledNumbers'][] = $number;
        $room['lastCall'] = [
            'number' => $number,
            'by' => $playerId,
            'at' => time(),
        ];

        foreach ($room['players'] as $idx => $player) {
            $board = $player['board'] ?? null;
            $room['players'][$idx]['lines'] = $this->countLines($board, $room['calledNumbers'], (int)($room['boardSize'] ?? 5));
            if ($room['players'][$idx]['lines'] >= 5 && $room['winnerId'] === null) {
                $room['winnerId'] = $player['id'];
            }
        }

        $room['turnIndex'] = $this->nextTurnIndex($room);
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function newGame(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $playerId = (string)($payload['playerId'] ?? '');

        if ($roomCode === '' || $playerId === '') {
            return $this->fail('Room code and player ID are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        if ($room['winnerId'] === null && !empty($room['calledNumbers'])) {
            return $this->fail('Game is still in progress.', 409);
        }

        $room['calledNumbers'] = [];
        $room['winnerId'] = null;
        $room['lastCall'] = null;

        foreach ($room['players'] as $idx => $player) {
            $room['players'][$idx]['lines'] = 0;
        }

        $room['startIndex'] = $this->nextStartIndex($room);
        $room['turnIndex'] = $room['startIndex'];
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    private function respond(array $payload): ResponseInterface
    {
        return $this->response->setJSON($payload);
    }

    private function fail(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok' => false,
            'error' => $message,
        ]);
    }

    private function normalizeRoomCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
        return substr($code, 0, 8);
    }

    private function normalizeBoardSize($size): ?int
    {
        $value = (int)$size;
        if (!in_array($value, [5, 7, 9], true)) {
            return null;
        }
        return $value;
    }

    private function normalizeBoard($board, int $size): ?array
    {
        if (!is_array($board) || count($board) !== $size) {
            return null;
        }

        $maxNumber = $size * $size;

        $numbers = [];
        $normalized = [];
        foreach ($board as $row) {
            if (!is_array($row) || count($row) !== $size) {
                return null;
            }
            $newRow = [];
            foreach ($row as $cell) {
                $value = (int)$cell;
                if ($value < 1 || $value > $maxNumber) {
                    return null;
                }
                if (in_array($value, $numbers, true)) {
                    return null;
                }
                $numbers[] = $value;
                $newRow[] = $value;
            }
            $normalized[] = $newRow;
        }

        return $normalized;
    }

    private function readRoom(string $roomCode): ?array
    {
        $row = $this->roomModel->where('room_code', $roomCode)->first();
        if (!$row) {
            return null;
        }

        $decoded = json_decode($row['state'] ?? '', true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeRoom(string $roomCode, array $room): void
    {
        $payload = [
            'room_code' => $roomCode,
            'state' => json_encode($room),
        ];

        $row = $this->roomModel->where('room_code', $roomCode)->first();
        if ($row) {
            $this->roomModel->update($row['id'], $payload);
            return;
        }

        $this->roomModel->insert($payload);
    }

    private function findPlayerIndex(array $room, string $playerId): int
    {
        foreach ($room['players'] as $idx => $player) {
            if (($player['id'] ?? '') === $playerId) {
                return (int)$idx;
            }
        }
        return -1;
    }

    private function allPlayersReady(array $room): bool
    {
        foreach ($room['players'] as $player) {
            if (empty($player['ready'])) {
                return false;
            }
        }
        return count($room['players']) > 0;
    }

    private function countLines(?array $board, array $calledNumbers, int $size): int
    {
        if (!is_array($board)) {
            return 0;
        }

        $called = array_flip($calledNumbers);
        $lines = 0;

        for ($r = 0; $r < $size; $r++) {
            $rowComplete = true;
            for ($c = 0; $c < $size; $c++) {
                $value = $board[$r][$c] ?? null;
                if ($value === null || !isset($called[$value])) {
                    $rowComplete = false;
                    break;
                }
            }
            if ($rowComplete) {
                $lines++;
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $colComplete = true;
            for ($r = 0; $r < $size; $r++) {
                $value = $board[$r][$c] ?? null;
                if ($value === null || !isset($called[$value])) {
                    $colComplete = false;
                    break;
                }
            }
            if ($colComplete) {
                $lines++;
            }
        }

        $diag1 = true;
        $diag2 = true;
        for ($i = 0; $i < $size; $i++) {
            $value1 = $board[$i][$i] ?? null;
            $value2 = $board[$i][$size - 1 - $i] ?? null;
            if ($value1 === null || !isset($called[$value1])) {
                $diag1 = false;
            }
            if ($value2 === null || !isset($called[$value2])) {
                $diag2 = false;
            }
        }
        if ($diag1) {
            $lines++;
        }
        if ($diag2) {
            $lines++;
        }

        return $lines;
    }

    private function buildState(array $room, string $playerId): array
    {
        $players = [];
        foreach ($room['players'] as $player) {
            $players[] = [
                'id' => $player['id'],
                'name' => $player['name'],
                'lines' => $player['lines'] ?? 0,
                'ready' => $player['ready'] ?? false,
            ];
        }

        $playerBoard = null;
        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex !== -1) {
            $playerBoard = $room['players'][$playerIndex]['board'] ?? null;
        }

        return [
            'roomCode' => $room['roomCode'],
            'boardSize' => (int)($room['boardSize'] ?? 5),
            'maxNumber' => $this->maxNumberForRoom($room),
            'players' => $players,
            'calledNumbers' => $room['calledNumbers'],
            'turnIndex' => $room['turnIndex'],
            'startIndex' => $room['startIndex'],
            'winnerId' => $room['winnerId'],
            'lastCall' => $room['lastCall'],
            'board' => $playerBoard,
        ];
    }

    private function maxNumberForRoom(array $room): int
    {
        $size = (int)($room['boardSize'] ?? 5);
        return $size * $size;
    }

    private function nextTurnIndex(array $room): int
    {
        $count = count($room['players']);
        if ($count === 0) {
            return 0;
        }
        $next = ((int)$room['turnIndex'] + 1) % $count;
        return $next;
    }

    private function nextStartIndex(array $room): int
    {
        $count = count($room['players']);
        if ($count === 0) {
            return 0;
        }
        $next = ((int)$room['startIndex'] + 1) % $count;
        return $next;
    }

    private function newPlayerId(): string
    {
        return 'p_' . bin2hex(random_bytes(8));
    }
}
