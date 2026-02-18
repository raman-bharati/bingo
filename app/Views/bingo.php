<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bingo Multiplayer</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bungee&family=Space+Grotesk:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('bingo.css') ?>">
</head>
<body>
  <main class="page">
    <section class="hero">
      <div>
        <div class="eyebrow">Multiplayer Bingo</div>
        <h1>Bingo in real time.</h1>
        <p class="lede">Build your 5x5 board, take turns calling numbers, and claim five lines first.</p>
      </div>
      <div class="panel" id="roomPanel">
        <div class="panel-title">Join a room</div>
        <label class="field">
          <span>Name</span>
          <input id="playerName" type="text" placeholder="Your name">
        </label>
        <label class="field">
          <span>Room code</span>
          <input id="roomCode" type="text" placeholder="E.g. BINGO1" maxlength="8">
        </label>
        <label class="field">
          <span>Board size</span>
          <select id="boardSize">
            <option value="5" selected>5 x 5</option>
            <option value="7">7 x 7</option>
            <option value="9">9 x 9</option>
          </select>
        </label>
        <div class="actions">
          <button id="createRoom" class="primary">Create room</button>
          <button id="joinRoom" class="ghost">Join room</button>
        </div>
        <div class="hint">Share the room code with friends.</div>
      </div>
    </section>

    <section class="board-section">
      <div class="board-wrap">
        <div class="board-header">
          <div>
            <div class="board-title">Your board</div>
            <div class="board-sub">Pick numbers or auto-generate.</div>
          </div>
          <div class="board-actions">
            <button id="autoFill">Auto-generate</button>
            <button id="clearBoard" class="ghost">Clear</button>
          </div>
        </div>
        <div class="board" id="board"></div>
        <div class="picker">
          <div class="picker-title">Number picker</div>
          <div class="picker-grid" id="picker"></div>
        </div>
        <button id="lockBoard" class="primary full" disabled>Lock board and ready</button>
      </div>

      <div class="status-wrap">
        <div class="status-card">
          <div class="status-title">Room</div>
          <div id="roomStatus" class="status-value">Not connected</div>
          <div class="status-meta" id="turnStatus">Waiting...</div>
        </div>
        <div class="status-card">
          <div class="status-title">Lines</div>
          <div id="linesStatus" class="status-value">0</div>
          <div id="lineLetters" class="status-meta">Need 5 lines to win.</div>
        </div>
        <div class="status-card">
          <div class="status-title">Last call</div>
          <div id="lastCall" class="status-value">None</div>
          <div class="status-meta" id="callMeta">Waiting for first call.</div>
        </div>
        <div class="status-card">
          <div class="status-title">Players</div>
          <div id="playerList" class="players"></div>
        </div>
        <div class="status-card">
          <div class="status-title">Call a number</div>
          <div class="call-grid" id="callGrid"></div>
          <button id="newGame" class="ghost full" disabled>Start next game</button>
        </div>
      </div>
    </section>
  </main>

  <script>
    window.BINGO_BASE = "<?= rtrim(base_url(), '/') ?>";
  </script>
  <script src="<?= base_url('bingo.js') ?>"></script>
</body>
</html>
