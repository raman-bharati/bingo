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
        $addBot = !empty($payload['addBot']);
        $botDifficulty = $this->normalizeBotDifficulty($payload['botDifficulty'] ?? 'easy');

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
            'wins' => 0,
        ];

        $room = [
            'roomCode' => $roomCode,
            'createdAt' => time(),
            'creatorId' => $playerId,
            'boardSize' => $boardSize,
            'players' => [$player],
            'calledNumbers' => [],
            'turnIndex' => 0,
            'startIndex' => 0,
            'winnerIds' => [],
            'lastCall' => null,
            'started' => false,
            'roomWins' => [],
            'callerWinsOnly' => false,
        ];

        if ($addBot) {
            $botId = $this->newBotId();
            $botBoard = $this->generateBotBoard($boardSize);
            $room['players'][] = [
                'id' => $botId,
                'name' => 'CPU (' . ucfirst($botDifficulty) . ')',
                'board' => $botBoard,
                'lines' => 0,
                'ready' => true,
                'wins' => 0,
                'isBot' => true,
                'difficulty' => $botDifficulty,
            ];
        }

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

        if ($roomCode === '' || $name === '') {
            return $this->fail('Room code and name are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $roomSize = (int)($room['boardSize'] ?? 5);

        if (!empty($room['calledNumbers'])) {
            return $this->fail('Game already started.', 409);
        }

        if (!empty($room['started'])) {
            return $this->fail('Game already started.', 409);
        }

        // Check if player name already exists in the room
        foreach ($room['players'] as $existingPlayer) {
            if (strtolower(trim($existingPlayer['name'])) === strtolower($name)) {
                return $this->fail('A player with this name is already in the room.', 409);
            }
        }

        $playerId = $this->newPlayerId();
        $normalizedBoard = $this->normalizeBoard($board, $roomSize);
        $player = [
            'id' => $playerId,
            'name' => $name,
            'board' => $normalizedBoard,
            'lines' => 0,
            'ready' => $normalizedBoard !== null,
            'wins' => 0,
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

        if (!empty($room['started'])) {
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

        if (!empty($room['winnerIds'])) {
            return $this->fail('Game is over.', 409);
        }

        if (empty($room['started'])) {
            return $this->fail('Game has not started.', 409);
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

        $room = $this->applyCalledNumber($room, $playerId, $number);

        // Auto-play bot turns until the game ends or a human turn is reached.
        while (empty($room['winnerIds']) && $this->isBotTurn($room)) {
            $bot = $room['players'][(int)$room['turnIndex']] ?? null;
            if (!is_array($bot)) {
                break;
            }

            $botNumber = $this->selectBotNumber($room, $bot);
            if ($botNumber === null) {
                break;
            }

            $room = $this->applyCalledNumber($room, (string)$bot['id'], $botNumber);
        }

        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function updateBoardSize(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $playerId = (string)($payload['playerId'] ?? '');
        $boardSize = $this->normalizeBoardSize($payload['boardSize'] ?? 0);

        if ($roomCode === '' || $playerId === '') {
            return $this->fail('Room code and player ID are required.', 400);
        }

        if ($boardSize === null) {
            return $this->fail('Board size must be 5, 7, or 9.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        if (($room['creatorId'] ?? '') !== $playerId) {
            return $this->fail('Only the host can change board size.', 403);
        }

        $gameInProgress = !empty($room['calledNumbers']) && empty($room['winnerIds']);
        if ($gameInProgress) {
            return $this->fail('Cannot change board size while game is in progress.', 409);
        }

        $currentSize = (int)($room['boardSize'] ?? 5);
        if ($currentSize === $boardSize) {
            return $this->respond([
                'ok' => true,
                'state' => $this->buildState($room, $playerId),
            ]);
        }

        $room['boardSize'] = $boardSize;
        $room['calledNumbers'] = [];
        $room['winnerIds'] = [];
        $room['lastCall'] = null;
        $room['started'] = false;

        foreach ($room['players'] as $idx => $player) {
            if (!empty($player['isBot'])) {
                $room['players'][$idx]['board'] = $this->generateBotBoard($boardSize);
                $room['players'][$idx]['ready'] = true;
            } else {
                $room['players'][$idx]['board'] = null;
                $room['players'][$idx]['ready'] = false;
            }
            $room['players'][$idx]['lines'] = 0;
        }

        $playerCount = count($room['players']);
        if ($playerCount > 0) {
            $startIndex = (int)($room['startIndex'] ?? 0) % $playerCount;
            if ($startIndex < 0) {
                $startIndex = 0;
            }
            $room['startIndex'] = $startIndex;
            $room['turnIndex'] = $startIndex;
        } else {
            $room['startIndex'] = 0;
            $room['turnIndex'] = 0;
        }

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

        if (empty($room['winnerIds']) && !empty($room['calledNumbers'])) {
            return $this->fail('Game is still in progress.', 409);
        }

        $room['calledNumbers'] = [];
        $room['winnerIds'] = [];
        $room['lastCall'] = null;
        $room['started'] = false;

        foreach ($room['players'] as $idx => $player) {
            $room['players'][$idx]['lines'] = 0;
            if (!empty($player['isBot'])) {
                $room['players'][$idx]['board'] = $this->generateBotBoard((int)($room['boardSize'] ?? 5));
                $room['players'][$idx]['ready'] = true;
            } else {
                $room['players'][$idx]['ready'] = false;
                $room['players'][$idx]['board'] = null;
            }
        }

        $room['startIndex'] = $this->nextStartIndex($room);
        $room['turnIndex'] = $room['startIndex'];
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function startGame(): ResponseInterface
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

        if (!empty($room['started'])) {
            return $this->fail('Game already started.', 409);
        }

        if (!empty($room['calledNumbers'])) {
            return $this->fail('Game already started.', 409);
        }

        if (!$this->allPlayersReady($room)) {
            return $this->fail('Waiting for all players to be ready.', 409);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        $room['started'] = true;
        $room['turnIndex'] = $room['startIndex'] ?? 0;

        // If a bot starts first, play bot turns immediately until a human turn or game end.
        while (empty($room['winnerIds']) && $this->isBotTurn($room)) {
            $bot = $room['players'][(int)$room['turnIndex']] ?? null;
            if (!is_array($bot)) {
                break;
            }

            $botNumber = $this->selectBotNumber($room, $bot);
            if ($botNumber === null) {
                break;
            }

            $room = $this->applyCalledNumber($room, (string)$bot['id'], $botNumber);
        }

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
        try {
            $row = $this->roomModel->where('room_code', $roomCode)->first();
            if (!$row) {
                return null;
            }

            $decoded = json_decode($row['state'] ?? '', true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return $this->readRoomFromFile($roomCode);
        }
    }

    private function writeRoom(string $roomCode, array $room): void
    {
        $payload = [
            'room_code' => $roomCode,
            'state' => json_encode($room),
        ];

        try {
            $row = $this->roomModel->where('room_code', $roomCode)->first();
            if ($row) {
                $this->roomModel->update($row['id'], $payload);
                return;
            }

            $this->roomModel->insert($payload);
        } catch (\Throwable $e) {
            $this->writeRoomToFile($roomCode, $room);
        }
    }

    private function readRoomFromFile(string $roomCode): ?array
    {
        $path = $this->getRoomStorePath();
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return null;
        }

        $room = $data[$roomCode] ?? null;
        return is_array($room) ? $room : null;
    }

    private function writeRoomToFile(string $roomCode, array $room): void
    {
        $path = $this->getRoomStorePath();
        $data = [];

        // Use file locking to prevent concurrent write issues
        $fp = fopen($path, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            try {
                rewind($fp);
                $contents = stream_get_contents($fp);
                if ($contents !== false && $contents !== '') {
                    $decoded = json_decode($contents, true);
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
                }

                $data[$roomCode] = $room;
                
                // Truncate and write
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($data));
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    private function getRoomStorePath(): string
    {
        return WRITEPATH . 'bingo_rooms.json';
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
                'wins' => $player['wins'] ?? 0,
                'isBot' => !empty($player['isBot']),
                'difficulty' => $player['difficulty'] ?? null,
            ];
        }

        $playerBoard = null;
        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex !== -1) {
            $playerBoard = $room['players'][$playerIndex]['board'] ?? null;
        }

        $started = !empty($room['started']) || !empty($room['calledNumbers']);

        $botBoards = [];
        if (!empty($room['winnerIds'])) {
            foreach ($room['players'] as $player) {
                if (empty($player['isBot']) || !is_array($player['board'] ?? null)) {
                    continue;
                }

                $botBoards[] = [
                    'id' => $player['id'],
                    'name' => $player['name'] ?? 'CPU',
                    'difficulty' => $player['difficulty'] ?? 'easy',
                    'board' => $player['board'],
                ];
            }
        }

        return [
            'roomCode' => $room['roomCode'],
            'creatorId' => $room['creatorId'] ?? null,
            'boardSize' => (int)($room['boardSize'] ?? 5),
            'maxNumber' => $this->maxNumberForRoom($room),
            'players' => $players,
            'calledNumbers' => $room['calledNumbers'],
            'turnIndex' => $room['turnIndex'],
            'startIndex' => $room['startIndex'],
            'winnerIds' => $room['winnerIds'] ?? [],
            'lastCall' => $room['lastCall'],
            'board' => $playerBoard,
            'botBoards' => $botBoards,
            'started' => $started,
            'callerWinsOnly' => !empty($room['callerWinsOnly']),
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

    private function newBotId(): string
    {
        return 'bot_' . bin2hex(random_bytes(8));
    }

    private function normalizeBotDifficulty($difficulty): string
    {
        $value = strtolower(trim((string)$difficulty));
        if (!in_array($value, ['easy', 'medium', 'hard', 'extreme'], true)) {
            return 'easy';
        }
        return $value;
    }

    private function generateBotBoard(int $size): array
    {
        $numbers = range(1, $size * $size);
        shuffle($numbers);
        $board = [];

        for ($r = 0; $r < $size; $r++) {
            $board[] = array_slice($numbers, $r * $size, $size);
        }

        return $board;
    }

    private function isBotTurn(array $room): bool
    {
        $turnIndex = (int)($room['turnIndex'] ?? 0);
        $player = $room['players'][$turnIndex] ?? null;
        return is_array($player) && !empty($player['isBot']);
    }

    private function selectBotNumber(array $room, array $bot): ?int
    {
        $maxNumber = $this->maxNumberForRoom($room);
        $called = array_flip($room['calledNumbers'] ?? []);
        $allUncalled = [];
        for ($n = 1; $n <= $maxNumber; $n++) {
            if (!isset($called[$n])) {
                $allUncalled[] = $n;
            }
        }

        if ($allUncalled === []) {
            return null;
        }

        $difficulty = $this->normalizeBotDifficulty($bot['difficulty'] ?? 'easy');
        $botBoard = $bot['board'] ?? null;

        if (!is_array($botBoard)) {
            return $allUncalled[array_rand($allUncalled)];
        }

        $botCandidates = [];
        foreach ($botBoard as $row) {
            foreach ((array)$row as $value) {
                $v = (int)$value;
                if ($v > 0 && !isset($called[$v])) {
                    $botCandidates[$v] = true;
                }
            }
        }
        $botCandidates = array_keys($botCandidates);

        if ($difficulty === 'easy') {
            // Easy: play to finish 5 lines quickly on its own board (no opponent blocking).
            $size = (int)($room['boardSize'] ?? 5);
            $candidatePool = $botCandidates !== [] ? $botCandidates : $allUncalled;
            $scores = [];
            $calledNumbers = $room['calledNumbers'] ?? [];

            foreach ($candidatePool as $candidate) {
                $scores[$candidate] = $this->scoreCandidateForBoard($botBoard, $calledNumbers, $candidate, $size, 8.0);
            }

            arsort($scores);
            $bestEasy = array_key_first($scores);
            if ($bestEasy !== null) {
                return (int)$bestEasy;
            }
            return $allUncalled[array_rand($allUncalled)];
        }

        if ($difficulty === 'medium') {
            if ($botCandidates !== []) {
                return $botCandidates[array_rand($botCandidates)];
            }
            return $allUncalled[array_rand($allUncalled)];
        }

        // Hard/Extreme: build trap lines first, then finish once traps exceed threshold.
        $size = (int)($room['boardSize'] ?? 5);
        $candidatePool = $botCandidates !== [] ? $botCandidates : $allUncalled;
        $calledNumbers = $room['calledNumbers'] ?? [];
        $lineStatsBefore = $this->getLineStats($botBoard, $calledNumbers, $size);
        $shouldFinish = $lineStatsBefore['nearComplete'] > 3;
        $scores = [];

        foreach ($candidatePool as $candidate) {
            $calledAfter = $calledNumbers;
            $calledAfter[] = $candidate;
            $lineStatsAfter = $this->getLineStats($botBoard, $calledAfter, $size);

            $deltaNear = $lineStatsAfter['nearComplete'] - $lineStatsBefore['nearComplete'];
            $deltaComplete = $lineStatsAfter['complete'] - $lineStatsBefore['complete'];

            if ($shouldFinish) {
                // Closing phase: prioritize ending the game quickly.
                $scores[$candidate] = ($deltaComplete * 2000)
                    + ($lineStatsAfter['complete'] * 200)
                    + ($this->scoreCandidateForBoard($botBoard, $calledNumbers, $candidate, $size, 12.0));
            } else {
                // Trap phase: keep many near-complete lines, avoid finishing too early.
                $scores[$candidate] = ($deltaNear * 800)
                    + ($lineStatsAfter['nearComplete'] * 120)
                    + ($this->scoreCandidateForBoard($botBoard, $calledNumbers, $candidate, $size, 6.0))
                    - ($deltaComplete * 3000);
            }
        }

        if ($difficulty === 'extreme') {
            // Extreme can "see" opponent boards and avoid helping them.
            foreach ($candidatePool as $candidate) {
                foreach ($room['players'] as $player) {
                    if (($player['id'] ?? '') === ($bot['id'] ?? '') || !is_array($player['board'] ?? null)) {
                        continue;
                    }

                    $opponentWeight = $shouldFinish ? 6.0 : 8.0;
                    $scores[$candidate] -= $this->scoreCandidateForBoard(
                        $player['board'],
                        $calledNumbers,
                        $candidate,
                        $size,
                        $opponentWeight
                    );
                }
            }
        }

        arsort($scores);
        $best = array_key_first($scores);
        if ($best === null) {
            return $allUncalled[array_rand($allUncalled)];
        }

        return (int)$best;
    }

    private function getLineStats(array $board, array $calledNumbers, int $size): array
    {
        $calledSet = array_flip($calledNumbers);
        $complete = 0;
        $nearComplete = 0;

        foreach ($this->getBoardLines($board, $size) as $line) {
            $calledCount = 0;
            foreach ($line as $value) {
                if ($value !== null && isset($calledSet[$value])) {
                    $calledCount++;
                }
            }

            if ($calledCount >= $size) {
                $complete++;
            } elseif ($calledCount === $size - 1) {
                $nearComplete++;
            }
        }

        return [
            'complete' => $complete,
            'nearComplete' => $nearComplete,
        ];
    }

    private function scoreCandidateForBoard(array $board, array $calledNumbers, int $candidate, int $size, float $base): float
    {
        $calledSet = array_flip($calledNumbers);
        $score = 0.0;

        foreach ($this->getBoardLines($board, $size) as $line) {
            if (!in_array($candidate, $line, true)) {
                continue;
            }

            $calledCount = 0;
            foreach ($line as $value) {
                if (isset($calledSet[$value])) {
                    $calledCount++;
                }
            }

            $score += pow($base, $calledCount);
        }

        return $score;
    }

    private function getBoardLines(array $board, int $size): array
    {
        $lines = [];

        for ($r = 0; $r < $size; $r++) {
            $lines[] = $board[$r] ?? [];
        }

        for ($c = 0; $c < $size; $c++) {
            $col = [];
            for ($r = 0; $r < $size; $r++) {
                $col[] = $board[$r][$c] ?? null;
            }
            $lines[] = $col;
        }

        $diag1 = [];
        $diag2 = [];
        for ($i = 0; $i < $size; $i++) {
            $diag1[] = $board[$i][$i] ?? null;
            $diag2[] = $board[$i][$size - 1 - $i] ?? null;
        }
        $lines[] = $diag1;
        $lines[] = $diag2;

        return $lines;
    }

    private function applyCalledNumber(array $room, string $callerId, int $number): array
    {
        $room['calledNumbers'][] = $number;
        $room['lastCall'] = [
            'number' => $number,
            'by' => $callerId,
            'at' => time(),
        ];

        $playersAt5Lines = [];
        foreach ($room['players'] as $idx => $player) {
            $board = $player['board'] ?? null;
            $room['players'][$idx]['lines'] = $this->countLines($board, $room['calledNumbers'], (int)($room['boardSize'] ?? 5));
            if ($room['players'][$idx]['lines'] >= 5) {
                $playersAt5Lines[] = $player['id'];
            }
        }

        if (empty($room['winnerIds']) && !empty($playersAt5Lines)) {
            $callerWinsOnly = !empty($room['callerWinsOnly']);
            if ($callerWinsOnly) {
                $room['winnerIds'] = in_array($callerId, $playersAt5Lines, true) ? [$callerId] : [];
            } else {
                $room['winnerIds'] = $playersAt5Lines;
            }

            foreach ($room['winnerIds'] as $winnerId) {
                $winnerIndex = $this->findPlayerIndex($room, $winnerId);
                if ($winnerIndex !== -1) {
                    $room['players'][$winnerIndex]['wins'] = (int)($room['players'][$winnerIndex]['wins'] ?? 0) + 1;
                }
            }
        }

        $room['turnIndex'] = $this->nextTurnIndex($room);
        return $room;
    }

    public function toggleRule(): ResponseInterface
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

        if (($room['creatorId'] ?? '') !== $playerId) {
            return $this->fail('Only the host can change game mode rules.', 403);
        }

        // Only allow rule change before game starts or after game ends
        if (!empty($room['started']) && empty($room['winnerIds'])) {
            return $this->fail('Cannot change rule during an active game.', 409);
        }

        $room['callerWinsOnly'] = !($room['callerWinsOnly'] ?? false);
        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function kickPlayer(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $roomCode = $this->normalizeRoomCode($payload['roomCode'] ?? '');
        $playerId = (string)($payload['playerId'] ?? '');
        $targetPlayerId = (string)($payload['targetPlayerId'] ?? '');

        if ($roomCode === '' || $playerId === '' || $targetPlayerId === '') {
            return $this->fail('Room code, player ID, and target player ID are required.', 400);
        }

        $room = $this->readRoom($roomCode);
        if ($room === null) {
            return $this->fail('Room not found.', 404);
        }

        $playerIndex = $this->findPlayerIndex($room, $playerId);
        if ($playerIndex === -1) {
            return $this->fail('Player not found.', 404);
        }

        // Only room creator can kick players
        $creatorId = $room['creatorId'] ?? null;
        if ($creatorId !== null && $playerId !== $creatorId) {
            return $this->fail('Only the room creator can kick players.', 403);
        }

        $targetIndex = $this->findPlayerIndex($room, $targetPlayerId);
        if ($targetIndex === -1) {
            return $this->fail('Target player not found.', 404);
        }

        if ($playerId === $targetPlayerId) {
            return $this->fail('Cannot kick yourself. Use leave room instead.', 400);
        }

        // Remove the player
        array_splice($room['players'], $targetIndex, 1);

        // Adjust turn index if needed
        if (count($room['players']) === 0) {
            $room['turnIndex'] = 0;
            $room['startIndex'] = 0;
        } else {
            // If removed player was before or at current turn, adjust turnIndex
            if ($targetIndex <= (int)$room['turnIndex']) {
                $room['turnIndex'] = max(0, (int)$room['turnIndex'] - 1);
            }
            // Ensure turnIndex is within bounds
            if ((int)$room['turnIndex'] >= count($room['players'])) {
                $room['turnIndex'] = 0;
            }
            if ((int)$room['startIndex'] >= count($room['players'])) {
                $room['startIndex'] = 0;
            }
        }

        // Remove from winners if they were a winner
        $room['winnerIds'] = array_values(array_filter($room['winnerIds'] ?? [], function($id) use ($targetPlayerId) {
            return $id !== $targetPlayerId;
        }));

        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'state' => $this->buildState($room, $playerId),
        ]);
    }

    public function leaveRoom(): ResponseInterface
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

        // Remove the player
        array_splice($room['players'], $playerIndex, 1);

        // Adjust turn index if needed
        if (count($room['players']) === 0) {
            // Last player left - could optionally delete room here
            $room['turnIndex'] = 0;
            $room['startIndex'] = 0;
            $room['started'] = false;
            $room['winnerIds'] = [];
            $room['calledNumbers'] = [];
        } else {
            // If leaving player was before or at current turn, adjust turnIndex
            if ($playerIndex <= (int)$room['turnIndex']) {
                $room['turnIndex'] = max(0, (int)$room['turnIndex'] - 1);
            }
            // Ensure turnIndex is within bounds
            if ((int)$room['turnIndex'] >= count($room['players'])) {
                $room['turnIndex'] = 0;
            }
            if ((int)$room['startIndex'] >= count($room['players'])) {
                $room['startIndex'] = 0;
            }
        }

        // Remove from winners if they were a winner
        $room['winnerIds'] = array_values(array_filter($room['winnerIds'] ?? [], function($id) use ($playerId) {
            return $id !== $playerId;
        }));

        $this->writeRoom($roomCode, $room);

        return $this->respond([
            'ok' => true,
            'left' => true,
        ]);
    }
}
