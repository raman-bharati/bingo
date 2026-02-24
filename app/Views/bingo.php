<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Primary Meta Tags -->
  <title>Bingo Multiplayer - Play Real-Time Bingo Online Free | 5x5, 7x7, 9x9 Boards</title>
  <meta name="title" content="Bingo Multiplayer - Play Real-Time Bingo Online Free">
  <meta name="description" content="Play multiplayer bingo online with friends in real-time. Create custom boards (5x5, 7x7, 9x9), take turns calling numbers, and compete for 5 lines. Free multiplayer bingo game with live updates!">
  <meta name="keywords" content="bingo, multiplayer bingo, online bingo, play bingo online, bingo game, real-time bingo, free bingo, bingo with friends">
  <meta name="author" content="Raman">
  <link rel="canonical" href="<?= base_url('/bingo') ?>">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= base_url('/bingo') ?>">
  <meta property="og:title" content="Bingo Multiplayer - Play Real-Time Bingo Online Free">
  <meta property="og:description" content="Play multiplayer bingo online with friends in real-time. Create custom boards, take turns calling numbers, and compete for 5 lines!">
  <meta property="og:image" content="<?= base_url('/bingo-og.png') ?>">
  <meta property="og:site_name" content="Bingo Multiplayer">
  
  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="<?= base_url('/bingo') ?>">
  <meta property="twitter:title" content="Bingo Multiplayer - Play Real-Time Bingo Online Free">
  <meta property="twitter:description" content="Play multiplayer bingo online with friends in real-time. Create custom boards, take turns calling numbers, and compete for 5 lines!">
  <meta property="twitter:image" content="<?= base_url('/bingo-og.png') ?>">
  
  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎯</text></svg>">
  
  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bungee&family=Space+Grotesk:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/bingo.css">
  
  <!-- Structured Data / JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "Bingo Multiplayer",
    "description": "Play multiplayer bingo online with friends in real-time. Create custom boards (5x5, 7x7, 9x9), take turns calling numbers, and compete for 5 lines.",
    "url": "<?= base_url('/bingo') ?>",
    "applicationCategory": "Game",
    "operatingSystem": "Any",
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "USD"
    },
    "author": {
      "@type": "Person",
      "name": "Raman"
    },
    "aggregateRating": {
      "@type": "AggregateRating",
      "ratingValue": "5",
      "ratingCount": "1"
    }
  }
  </script>
</head>
<body>
  <main class="page">
    <section class="hero">
      <div>
        <div class="eyebrow">Multiplayer Bingo</div>
        <h1>Bingo in real time.</h1>
        <p class="lede">Build your board, take turns calling numbers, and claim five lines first.</p>
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
            <div class="board-title-row">
              <div class="board-title">Your board</div>
              <span id="boardDirtyIndicator" class="board-dirty" aria-live="polite" hidden>editing…</span>
            </div>
            <div class="board-sub">Pick numbers or auto-generate.</div>
          </div>
          <div class="board-actions">
            <button id="autoFill">Auto-generate</button>
            <button id="clearBoard" class="ghost">Clear</button>
            <button id="lockBoard" class="primary" disabled>Lock & ready</button>
          </div>
        </div>
        <div class="board" id="board"></div>
        <div class="picker">
          <div class="picker-title">Number picker</div>
          <div class="picker-grid" id="picker"></div>
        </div>
      </div>

      <div class="status-wrap">
        <div class="status-card">
          <div class="status-title">Room</div>
          <div id="roomStatus" class="status-value">Not connected</div>
          <div class="status-meta" id="turnStatus">Waiting...</div>
        </div>
        <div class="status-card">
          <div class="status-title">Players</div>
          <div id="playerList" class="players"></div>
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
      </div>

      <div class="leaderboard-wrap">
        <div class="leaderboard-card">
          <div class="leaderboard-title">🏆 Leaderboard</div>
          <div id="leaderboard" class="leaderboard-list"></div>
          <button id="newGame" class="ghost full" disabled>Start game</button>
          <button id="toggleRule" class="ghost full" style="display: none;">Rule: All Can Win</button>
          <button id="leaveRoom" class="ghost full" style="display: none;">Leave Room</button>
        </div>
        <div class="howto-card">
          <div class="howto-title">📖 How to Play</div>
          <ul class="howto-list">
            <li>Fill your board with unique numbers (1-25 for 5×5)</li>
            <li>Lock your board when ready</li>
            <li>Take turns calling numbers by clicking them</li>
            <li>Complete 5 lines first (rows/columns/diagonals) to win!</li>
            <li>Completed lines turn green</li>
            <li><strong>Caller Wins Only:</strong> Only the player calling can win on their turn</li>
          </ul>
        </div>
      </div>
    </section>

    <footer class="footer">
      <div class="footer-content">
        <p>&copy; 2026 Made by Raman. All rights reserved.</p>
      </div>
    </footer>
  </main>

  <div id="winnerModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="celebration">
        <div class="confetti"></div>
        <h2 id="winnerName"></h2>
        <p id="winnerMessage">🎉 WINS! 🎉</p>
        <div id="winnersList" class="winners-list"></div>
        <button id="closeWinnerModal" class="primary">Continue</button>
      </div>
    </div>
  </div>

  <script>
    window.BINGO_BASE = window.location.origin;
    
    // Register service worker for offline support
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
          console.log('Service worker registration failed');
        });
      });
    }
    
    // Global error handler
    window.addEventListener('error', (event) => {
      console.error('Global error:', event.error);
      // Optionally send to error tracking service
    });
    
    window.addEventListener('unhandledrejection', (event) => {
      console.error('Unhandled promise rejection:', event.reason);
      // Optionally send to error tracking service
    });
  </script>
  <script src="/bingo.js"></script>
</body>
</html>
