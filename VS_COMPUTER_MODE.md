# Adding a VS Computer (Bot) Mode to Bingo

This guide explains exactly how to implement a CPU opponent for the existing multiplayer Bingo game. The game uses a **CodeIgniter 4 PHP backend** with JSON/MySQL room state and a **vanilla JS frontend** that polls for state changes.

---

## Architecture Overview (Quick Recap)

- All game state lives server-side in a room object (DB or `writable/bingo-rooms/`).
- The frontend polls `GET /bingo/room/state` every 1–5 s and calls `applyState()` to sync.
- Turns are controlled by `turnIndex` — an index into the `players[]` array.
- After every `POST /bingo/room/call`, the server advances `turnIndex` to the next player.

---

## How Bot Turns Will Work

When `turnIndex` lands on a bot player after a human's turn, the server **automatically executes the bot's turn** within the same `callNumber` request cycle (no separate endpoint needed). The polling client will see the game already advanced past the bot when it next polls.

---

## Step 1 — Backend: Mark Players as Bots

### 1a. Add bot fields to the room state

In `app/Controllers/BingoController.php`, locate `createRoom()`. Accept optional bot parameters in the payload and inject a bot player before returning:

```php
// Inside createRoom(), after building the initial $room array:
$addBot = (bool)($payload['addBot'] ?? false);
$botDifficulty = in_array($payload['botDifficulty'] ?? '', ['easy', 'medium', 'hard'])
    ? $payload['botDifficulty']
    : 'easy';

if ($addBot) {
    $botId = 'bot_' . bin2hex(random_bytes(8));
    $botBoard = $this->generateBotBoard($room['boardSize']);
    $room['players'][] = [
        'id'         => $botId,
        'name'       => 'CPU (' . ucfirst($botDifficulty) . ')',
        'board'      => $botBoard,
        'lines'      => 0,
        'ready'      => true,   // bot is always ready
        'wins'       => 0,
        'isBot'      => true,
        'difficulty' => $botDifficulty,
    ];
}
```

### 1b. Add a `generateBotBoard()` helper method

Board layout is part of the strategy. A number sitting at the intersection of a row, a column, and a diagonal can complete **3 lines at once** when called — by anyone, not just the bot. So the hard bot should place its most "central" numbers at high-intersection positions (center and corners of the grid).

```php
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

/**
 * Pivot-aware board generation for hard difficulty.
 * Places the highest-value "bait" numbers at positions on the most lines
 * (center = row+col+2diags, corners = row+col+1diag).
 * When those positions get called by anyone, multiple lines complete at once.
 */
private function generateHardBotBoard(int $size): array
{
    // Rank positions by how many lines they belong to
    $positionLineCount = [];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            $count = 2; // always on 1 row + 1 col
            if ($r === $c) $count++;              // main diagonal
            if ($r + $c === $size - 1) $count++;  // anti-diagonal
            $positionLineCount["$r,$c"] = $count;
        }
    }
    arsort($positionLineCount);

    // Assign numbers 1..N² to positions: highest numbers go to highest-intersection spots
    // (higher numbers = more likely to be called by the bot's own greedy strategy)
    $numbers = range(1, $size * $size);
    sort($numbers); // ascending
    $board = array_fill(0, $size, array_fill(0, $size, 0));
    $positions = array_reverse(array_keys($positionLineCount)); // low-intersection first
    foreach ($positions as $idx => $key) {
        [$r, $c] = explode(',', $key);
        $board[(int)$r][(int)$c] = $numbers[$idx];
    }
    return $board;
}
```

> Use `generateHardBotBoard()` instead of `generateBotBoard()` when difficulty is `hard`.

### 1c. Auto-execute bot turns inside `callNumber()`

At the end of `callNumber()`, after advancing `turnIndex` and before writing the room + returning, add:

```php
// Auto-play bot turns
while (
    empty($room['winnerIds']) &&
    !empty($room['started']) &&
    ($room['players'][$room['turnIndex']]['isBot'] ?? false)
) {
    $room = $this->executeBotTurn($room);
}

$this->writeRoom($roomCode, $room);
return $this->respond(['ok' => true, 'state' => $this->buildState($room, $playerId)]);
```

> **Remove** the `writeRoom` / `respond` calls that were originally at the end of `callNumber()` and replace them with the block above.

### 1d. Add `executeBotTurn()` helper method

```php
private function executeBotTurn(array $room): array
{
    $turnIndex  = $room['turnIndex'];
    $bot        = $room['players'][$turnIndex];
    $difficulty = $bot['difficulty'] ?? 'easy';
    $maxNumber  = $room['boardSize'] ** 2;
    $called     = $room['calledNumbers'];
    $uncalled   = array_values(array_diff(range(1, $maxNumber), $called));

    if (empty($uncalled)) {
        return $room; // no numbers left
    }

    if ($difficulty === 'hard') {
        // Pick the uncalled number that appears on the most partial (incomplete) lines
        $number = $this->botPickSmart($bot['board'], $uncalled, $called, $room['boardSize']);
    } else {
        // Easy / medium: random uncalled number
        $number = $uncalled[array_rand($uncalled)];
    }

    $room['calledNumbers'][] = $number;
    $room['lastCall'] = [
        'number' => $number,
        'by'     => $bot['id'],
        'at'     => time(),
    ];

    // Recalculate lines for all players
    foreach ($room['players'] as $i => $player) {
        if (!empty($player['board'])) {
            $room['players'][$i]['lines'] = $this->countLines(
                $player['board'],
                $room['calledNumbers'],
                $room['boardSize']
            );
        }
    }

    // Check for winners
    if (empty($room['winnerIds'])) {
        $threshold = 5; // same threshold as the rest of the game
        $winners   = [];
        foreach ($room['players'] as $player) {
            if ($player['lines'] >= $threshold) {
                $winners[] = $player['id'];
            }
        }
        if (!empty($winners)) {
            if (!empty($room['callerWinsOnly'])) {
                $winners = in_array($bot['id'], $winners) ? [$bot['id']] : [];
            }
            $room['winnerIds'] = $winners;
            foreach ($room['players'] as $i => $player) {
                if (in_array($player['id'], $winners)) {
                    $room['players'][$i]['wins']++;
                }
            }
        }
    }

    // Advance turn
    $room['turnIndex'] = ($turnIndex + 1) % count($room['players']);

    return $room;
}
```

### 1e. Add `botPickSmart()` helper (hard difficulty)

The key insight: **you can win from someone else's call**. A number sitting at the intersection of a nearly-complete row + column + diagonal is a "pivot" — calling it (or having it called by the opponent) completes multiple lines simultaneously. The bot should:

1. **Prioritize pivot completions** — score a candidate number by how many of the bot's own lines it would *complete* (exponentially, not linearly).
2. **Penalize helping the opponent** — subtract score for each of the opponent's lines the same number would advance.
3. **Ignore dead lines** — lines that are already complete or hopelessly far from done don't count.

```php
/**
 * Score each uncalled number and return the best one to call.
 *
 * Scoring per candidate number n:
 *   +10^(called_in_line) for each bot line containing n  (exponential: nearly-complete lines score huge)
 *   -5^(called_in_line)  for each opponent line containing n (penalise helping opponent)
 *
 * This naturally surfaces "pivot" numbers: a number on a row with 4/5 called
 * AND a column with 4/5 called scores 10^4 + 10^4 = 20000, far above anything else.
 * Meanwhile if that same number also sits on 2 of the opponent's near-complete lines
 * it gets penalised 5^4 + 5^4 = 1250 — still worth calling, but the bot prefers a
 * pivot that doesn't also give the opponent lines.
 */
private function botPickSmart(array $botBoard, array $uncalled, array $called, int $size, array $opponentBoards = []): int
{
    $calledSet   = array_flip($called);
    $uncalledSet = array_flip($uncalled);
    $scores      = array_fill_keys($uncalled, 0.0);

    // Build line lists for the bot
    $botLines = $this->getBoardLines($botBoard, $size);

    foreach ($botLines as $line) {
        $lineUncalled = array_filter($line, fn($v) => isset($uncalledSet[$v]));
        if (empty($lineUncalled)) continue; // already complete or no bot numbers here

        $calledCount = count(array_filter($line, fn($v) => isset($calledSet[$v])));
        $bonus = pow(10, $calledCount); // exponential: 1, 10, 100, 1000, 10000

        foreach ($lineUncalled as $v) {
            $scores[$v] += $bonus;
        }
    }

    // Penalise numbers that also advance opponent lines
    foreach ($opponentBoards as $oppBoard) {
        $oppLines = $this->getBoardLines($oppBoard, $size);
        foreach ($oppLines as $line) {
            $lineUncalled = array_filter($line, fn($v) => isset($uncalledSet[$v]));
            if (empty($lineUncalled)) continue;

            $calledCount = count(array_filter($line, fn($v) => isset($calledSet[$v])));
            $penalty = pow(5, $calledCount); // penalty grows but slower than reward

            foreach ($lineUncalled as $v) {
                $scores[$v] -= $penalty;
            }
        }
    }

    arsort($scores);
    return (int) array_key_first($scores);
}

/** Returns all rows, columns, and diagonals for a board as flat arrays. */
private function getBoardLines(array $board, int $size): array
{
    $lines = [];
    for ($r = 0; $r < $size; $r++) {
        $lines[] = $board[$r];
    }
    for ($c = 0; $c < $size; $c++) {
        $col = [];
        for ($r = 0; $r < $size; $r++) {
            $col[] = $board[$r][$c];
        }
        $lines[] = $col;
    }
    $d1 = $d2 = [];
    for ($i = 0; $i < $size; $i++) {
        $d1[] = $board[$i][$i];
        $d2[] = $board[$i][$size - 1 - $i];
    }
    $lines[] = $d1;
    $lines[] = $d2;
    return $lines;
}
```

**Important:** pass the opponent boards to `botPickSmart()` when calling it in `executeBotTurn()`:

```php
$opponentBoards = [];
foreach ($room['players'] as $p) {
    if ($p['id'] !== $bot['id'] && !empty($p['board'])) {
        $opponentBoards[] = $p['board'];
    }
}
$number = $this->botPickSmart($bot['board'], $uncalled, $called, $room['boardSize'], $opponentBoards);
```

---

## Step 2 — Backend: Guard New Game & Start Game for Bots

### `startGame()` — bots are always ready, so no change needed.

The existing "all players must be ready" check already works because the bot was inserted with `ready: true`.

### `newGame()` — regenerate the bot's board on reset

In `newGame()`, after clearing each player, check for bots and assign a fresh board:

```php
foreach ($room['players'] as $i => $player) {
    $room['players'][$i]['board'] = null;
    $room['players'][$i]['lines'] = 0;
    $room['players'][$i]['ready'] = false;
    // Bots get a new board automatically
    if (!empty($player['isBot'])) {
        $room['players'][$i]['board'] = $this->generateBotBoard($room['boardSize']);
        $room['players'][$i]['ready'] = true;
    }
}
```

### `buildState()` — expose `isBot` to the frontend

Make sure the player objects returned to the client include the `isBot` flag so the frontend can hide the kick button and show "CPU is thinking…":

```php
// Inside the player-mapping loop in buildState():
'isBot' => !empty($player['isBot']),
```

---

## Step 3 — Frontend: `bingo.js` Changes

### 3a. "Add CPU opponent" checkbox in the create-room UI

In `bingo.php` (the view), add a checkbox near the create-room form:

```html
<label class="checkbox-label">
  <input type="checkbox" id="addBot"> Add CPU opponent
</label>
<select id="botDifficulty">
  <option value="easy">Easy</option>
  <option value="medium">Medium</option>
  <option value="hard">Hard</option>
</select>
```

In `bingo.js`, update `handleRoomAction("create")` to include the bot options:

```js
const addBot = document.getElementById("addBot")?.checked ?? false;
const botDifficulty = document.getElementById("botDifficulty")?.value ?? "easy";

// Add to the fetch body:
body: JSON.stringify({
  roomCode, name, boardSize, board,
  addBot,
  botDifficulty,
})
```

### 3b. Show "CPU is thinking…" status

In `renderStatus()` (or wherever turn text is rendered), add:

```js
const currentPlayer = state.players[state.turnIndex];
if (currentPlayer?.isBot) {
  statusEl.textContent = `${currentPlayer.name} is thinking…`;
}
```

### 3c. Hide kick button for bots

In `renderPlayerList()`, where kick buttons are rendered:

```js
// Only show kick for human players (host kicking a non-host, non-bot player)
if (!player.isBot && isHost && player.id !== state.playerId) {
  // render kick button
}
```

### 3d. No manual turn input needed

Because the bot's turn is resolved **server-side** inside the same `callNumber` response, the poller will automatically pick up the already-advanced state. No client-side bot loop is needed.

---

## Step 4 — Route (No Changes Needed)

All bot logic is handled inside existing endpoints (`createRoom`, `callNumber`, `newGame`). No new routes required.

---

## How Passive Winning Works (and Why It Matters for the Bot)

In this game you **do not need to call the winning number yourself**. When any player calls a number, it is marked on *everyone's* board. This means:

- You can set up 4 of 5 numbers in a line and wait — if the opponent calls the fifth number, you win.
- A "pivot" number sits at the intersection of a row, column, and/or diagonal. When it is called (by anyone), it can advance or complete **2–3 lines simultaneously**, winning the game in a single call.
- Skilled players intentionally keep multiple lines partially complete and arrange their board so one pivot number would finish them all.

The hard bot is designed around this reality:
- Its board places high-value numbers at intersection-heavy positions (center, corners).
- `botPickSmart()` scores candidates **exponentially** by how close to completion each line is (`10^calledCount`), so a nearly-complete line is worth far more than a fresh one.
- A number that sits on TWO nearly-complete lines (the pivot scenario) gets `10^4 + 10^4 = 20,000` — dwarfing everything else — so the bot always grabs the pivot call.
- Each opponent's near-complete lines subtract score (`5^calledCount`) so the bot avoids handing the human a win.

## Difficulty Summary

| Difficulty | Bot Strategy |
|---|---|
| **Easy** | Picks a completely random uncalled number. Weak — will rarely win unless lucky. |
| **Medium** | Same as easy currently (upgrade path: pick a random number that is at least on its own board). |
| **Hard** | Exponential pivot scoring + opponent penalty. Actively hunts multi-line completions and avoids helping the human. Competitive and capable of winning. |

---

## File Change Checklist

| File | What to change |
|---|---|
| `app/Controllers/BingoController.php` | Add bot injection in `createRoom()`, auto-turn loop in `callNumber()`, board reset in `newGame()`, `isBot` flag in `buildState()`. Add `generateBotBoard()`, `executeBotTurn()`, `botPickSmart()` private methods. |
| `app/Views/bingo.php` | Add "Add CPU opponent" checkbox + difficulty select near create-room form. |
| `public/bingo.js` | Pass `addBot`/`botDifficulty` in create payload; update status text and player list rendering for bots. |
| `public/bingo.css` | Optionally add a `.player-bot` style to visually distinguish CPU in the player list. |

---

## Testing Checklist

- [ ] Create a room with "Add CPU opponent" checked → CPU player appears in the player list immediately with `ready: true`.
- [ ] Lock your board and start the game → game starts (CPU doesn't block the "all ready" check).
- [ ] Call a number on your turn → state polls show the CPU's turn was already resolved.
- [ ] CPU wins → winner modal should show the CPU name correctly.
- [ ] Start next game → CPU gets a new board automatically, game can restart without issues.
- [ ] Hard difficulty → CPU visibly prefers numbers that advance its own partial lines.
- [ ] Kick button is **not** shown for the CPU player.
