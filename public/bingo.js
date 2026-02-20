const DEFAULT_SIZE = 5;
const LINE_LETTERS = ["B", "I", "N", "G", "O"];
const BASE_URL = typeof window !== "undefined" && window.BINGO_BASE ? window.BINGO_BASE : "";

const state = {
  roomCode: "",
  playerId: "",
  playerName: "",
  boardSize: DEFAULT_SIZE,
  maxNumber: DEFAULT_SIZE * DEFAULT_SIZE,
  board: createEmptyBoard(DEFAULT_SIZE),
  selectedCell: null,
  inputBuffer: "",
  calledNumbers: [],
  turnIndex: 0,
  startIndex: 0,
  players: [],
  winnerId: null,
  lastCall: null,
  started: false,
  completedLines: [],
  pollTimer: null,
  lastTapAt: 0,
  lastTapCell: null,
  boardDirty: false,
};

const elements = {
  playerName: document.getElementById("playerName"),
  roomCode: document.getElementById("roomCode"),
  boardSize: document.getElementById("boardSize"),
  createRoom: document.getElementById("createRoom"),
  joinRoom: document.getElementById("joinRoom"),
  autoFill: document.getElementById("autoFill"),
  clearBoard: document.getElementById("clearBoard"),
  board: document.getElementById("board"),
  picker: document.getElementById("picker"),
  lockBoard: document.getElementById("lockBoard"),
  roomStatus: document.getElementById("roomStatus"),
  turnStatus: document.getElementById("turnStatus"),
  linesStatus: document.getElementById("linesStatus"),
  lineLetters: document.getElementById("lineLetters"),
  lastCall: document.getElementById("lastCall"),
  callMeta: document.getElementById("callMeta"),
  playerList: document.getElementById("playerList"),
  callGrid: document.getElementById("callGrid"),
  newGame: document.getElementById("newGame"),
  winnerModal: document.getElementById("winnerModal"),
  winnerName: document.getElementById("winnerName"),
  leaderboard: document.getElementById("leaderboard"),
  boardDirtyIndicator: document.getElementById("boardDirtyIndicator"),
};

const storage = {
  playerId: localStorage.getItem("bingo.playerId") || "",
};

init();

function init() {
  elements.playerName.value = localStorage.getItem("bingo.playerName") || "";
  elements.boardSize.value = String(state.boardSize);
  renderBoard();
  renderPicker();
  renderCallGrid();

  elements.createRoom.addEventListener("click", () => handleRoomAction("create"));
  elements.joinRoom.addEventListener("click", () => handleRoomAction("join"));
  elements.boardSize.addEventListener("change", handleBoardSizeChange);
  elements.autoFill.addEventListener("click", autoFillBoard);
  elements.clearBoard.addEventListener("click", clearBoard);
  elements.lockBoard.addEventListener("click", lockBoard);
  elements.newGame.addEventListener("click", handleGameButton);
  document.getElementById("closeWinnerModal").addEventListener("click", closeWinnerModal);
  document.addEventListener("keydown", handleKeyInput);
}

function createEmptyBoard(size) {
  return Array.from({ length: size }, () => Array.from({ length: size }, () => null));
}

function renderBoard() {
  if (!elements.board) {
    console.warn("Board element not found");
    return;
  }
  elements.board.innerHTML = "";
  elements.board.style.setProperty("--board-size", state.boardSize);
  elements.board.dataset.size = String(state.boardSize);
  elements.board.style.position = "relative";
  
  state.board.forEach((row, r) => {
    row.forEach((cell, c) => {
      const div = document.createElement("div");
      div.className = "board-cell";
      if (state.selectedCell && state.selectedCell[0] === r && state.selectedCell[1] === c) {
        div.classList.add("selected");
      }
      if (isMarked(cell)) {
        div.classList.add("marked");
      }
      const lineInfo = isInCompletedLine(r, c);
      if (lineInfo) {
        applyLineHighlight(div, lineInfo);
      }
      if (state.selectedCell && state.selectedCell[0] === r && state.selectedCell[1] === c && state.inputBuffer) {
        div.textContent = state.inputBuffer;
      } else {
        div.textContent = cell ? String(cell) : "";
      }
      div.addEventListener("click", () => handleCellClick(r, c));
      div.addEventListener("dblclick", () => handleCellCall(r, c));
      div.addEventListener("touchend", (event) => handleCellTouch(event, r, c), { passive: false });
      elements.board.appendChild(div);
    });
  });
  updateBoardDirtyIndicator();
}

function applyLineHighlight(cell, lineType) {
  cell.dataset.lineType = lineType;
  cell.style.background = "rgba(242, 107, 79, 0.16)";
  cell.style.borderColor = "var(--accent-dark)";
  cell.style.boxShadow = "0 0 0 2px rgba(242, 107, 79, 0.2)";
}

function renderPicker() {
  if (!elements.picker) {
    console.warn("Picker element not found");
    return;
  }
  elements.picker.innerHTML = "";
  for (let i = 1; i <= state.maxNumber; i++) {
    const button = document.createElement("button");
    button.className = "picker-button";
    button.textContent = String(i);
    if (boardHasNumber(i)) {
      button.classList.add("used");
      button.disabled = true;
    }
    button.addEventListener("click", () => placeNumber(i));
    elements.picker.appendChild(button);
  }
}

function renderCallGrid() {
  if (!elements.callGrid) {
    console.warn("Call grid element not found");
    return;
  }
  elements.callGrid.innerHTML = "";
  for (let i = 1; i <= state.maxNumber; i++) {
    const button = document.createElement("button");
    button.className = "call-button";
    button.textContent = String(i);
    if (state.calledNumbers.includes(i)) {
      button.classList.add("called");
      button.disabled = true;
    }
    button.addEventListener("click", () => callNumber(i));
    elements.callGrid.appendChild(button);
  }
}

function handleCellClick(row, col) {
  if (state.started) {
    handleCellCall(row, col);
    return;
  }
  selectCell(row, col);
}

function handleCellCall(row, col) {
  if (!state.started || !state.roomCode || !state.playerId) {
    return;
  }
  const number = state.board[row][col];
  if (!Number.isInteger(number) || state.calledNumbers.includes(number)) {
    return;
  }
  callNumber(number);
}

function handleCellTouch(event, row, col) {
  event.preventDefault();
  const now = Date.now();
  const sameCell = state.lastTapCell && state.lastTapCell[0] === row && state.lastTapCell[1] === col;
  const isDoubleTap = sameCell && now - state.lastTapAt < 300;

  state.lastTapAt = now;
  state.lastTapCell = [row, col];

  if (isDoubleTap) {
    handleCellCall(row, col);
    return;
  }

  handleCellClick(row, col);
}

function selectCell(row, col) {
  if (state.started || state.calledNumbers.length > 0) {
    return;
  }
  state.selectedCell = [row, col];
  state.inputBuffer = "";
  renderBoard();
}

function placeNumber(number) {
  if (!state.selectedCell) {
    return;
  }
  if (number < 1 || number > state.maxNumber) {
    return;
  }
  if (boardHasNumber(number, state.selectedCell)) {
    alert("Each number must be unique.");
    return;
  }
  const [row, col] = state.selectedCell;
  state.board[row][col] = number;
  state.boardDirty = true;
  state.selectedCell = null;
  state.inputBuffer = "";
  renderBoard();
  renderPicker();
  updateLockButton();
}

function autoFillBoard() {
  const numbers = shuffleArray(Array.from({ length: state.maxNumber }, (_, i) => i + 1)).slice(0, state.boardSize * state.boardSize);
  const nextBoard = createEmptyBoard(state.boardSize);
  let idx = 0;
  for (let r = 0; r < state.boardSize; r++) {
    for (let c = 0; c < state.boardSize; c++) {
      nextBoard[r][c] = numbers[idx++];
    }
  }
  state.board = nextBoard;
  state.boardDirty = true;
  state.selectedCell = null;
  state.inputBuffer = "";
  renderBoard();
  renderPicker();
  updateLockButton();
}

function clearBoard() {
  state.board = createEmptyBoard(state.boardSize);
  state.selectedCell = null;
  state.inputBuffer = "";
  state.boardDirty = true;
  renderBoard();
  renderPicker();
  updateLockButton();
}

function updateLockButton() {
  const full = state.board.flat().every((cell) => Number.isInteger(cell));
  elements.lockBoard.disabled = !full || !state.playerId || state.calledNumbers.length > 0;
  updateBoardActions();
}

function updateBoardActions() {
  const me = state.players.find((player) => player.id === state.playerId);
  const isLocked = !!(me && me.ready);
  if (elements.autoFill) {
    elements.autoFill.disabled = isLocked;
  }
  if (elements.clearBoard) {
    elements.clearBoard.disabled = isLocked;
  }
}

function updateBoardDirtyIndicator() {
  if (!elements.boardDirtyIndicator) {
    return;
  }
  elements.boardDirtyIndicator.hidden = !state.boardDirty;
}

function handleBoardSizeChange() {
  const nextSize = Number(elements.boardSize.value);
  if (![5, 7, 9].includes(nextSize)) {
    elements.boardSize.value = String(state.boardSize);
    return;
  }

  if (state.roomCode && state.calledNumbers.length > 0) {
    alert("Board size is locked once a game starts.");
    elements.boardSize.value = String(state.boardSize);
    return;
  }

  setBoardSize(nextSize, true);
}

function setBoardSize(size, resetBoard) {
  state.boardSize = size;
  state.maxNumber = size * size;
  if (resetBoard) {
    state.board = createEmptyBoard(size);
    state.selectedCell = null;
    state.inputBuffer = "";
    state.boardDirty = true;
  }
  elements.boardSize.value = String(size);
  renderBoard();
  renderPicker();
  renderCallGrid();
  updateLockButton();
}

function handleKeyInput(event) {
  const target = event.target;
  if (target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.tagName === "SELECT")) {
    return;
  }
  if (!state.selectedCell) {
    return;
  }

  if (event.key === "Escape") {
    state.selectedCell = null;
    state.inputBuffer = "";
    renderBoard();
    return;
  }

  if (event.key === "Backspace") {
    if (state.inputBuffer.length > 0) {
      state.inputBuffer = state.inputBuffer.slice(0, -1);
      renderBoard();
    }
    return;
  }

  if (event.key === "Enter") {
    commitBuffer();
    return;
  }

  if (!/^[0-9]$/.test(event.key)) {
    return;
  }

  const maxDigits = String(state.maxNumber).length;
  if (state.inputBuffer.length >= maxDigits) {
    state.inputBuffer = "";
  }
  state.inputBuffer += event.key;
  renderBoard();
}

function commitBuffer() {
  if (!state.selectedCell || !state.inputBuffer) {
    return;
  }
  const value = Number(state.inputBuffer);
  if (!Number.isInteger(value) || value < 1 || value > state.maxNumber) {
    state.inputBuffer = "";
    renderBoard();
    return;
  }
  if (boardHasNumber(value, state.selectedCell)) {
    state.inputBuffer = "";
    renderBoard();
    alert("Each number must be unique.");
    return;
  }
  placeNumber(value);
}

function handleRoomAction(action) {
  const name = elements.playerName.value.trim();
  const roomCode = elements.roomCode.value.trim();
  if (!name || !roomCode) {
    alert("⚠️ Error: Enter your name and room code.");
    return;
  }
  state.playerName = name;
  localStorage.setItem("bingo.playerName", name);

  const boardReady = state.board.flat().every((cell) => Number.isInteger(cell));
  const payload = {
    name,
    roomCode,
    boardSize: state.boardSize,
    board: boardReady ? state.board : null,
  };

  fetch(`${BASE_URL}/bingo/room/${action}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }
      return res.json();
    })
    .then((data) => {
      if (!data.ok) {
        let errorMsg = data.error || "Unknown error";
        // Add helpful suggestions for common errors
        if (errorMsg === "Room already exists." && action === "create") {
          errorMsg += "\n\nTry a different room code.";
        } else if (errorMsg === "Room not found." && action === "join") {
          errorMsg += "\n\nAsk the creator for the correct room code.";
        } else if (errorMsg === "Game already started.") {
          errorMsg += "\n\nYou cannot join or change your board once the game has started.";
        } else if (errorMsg === "Board size does not match the room.") {
          errorMsg += "\n\nSelect the same board size as the room.";
        }
        alert(`⚠️ ${action === 'create' ? 'Create Room' : 'Join Room'} Error:\n\n${errorMsg}`);
        console.error(`Room ${action} error:`, data);
        return;
      }
      state.playerId = data.playerId;
      state.roomCode = roomCode;
      localStorage.setItem("bingo.playerId", state.playerId);
      applyState(data.state);
      startPolling();
    })
    .catch((err) => {
      alert(`⚠️ ${action === 'create' ? 'Create Room' : 'Join Room'} Failed:\n\n${err.message}\n\nCheck console for details.`);
      console.error(`Room ${action} exception:`, err);
    });
}

function lockBoard() {
  if (!state.playerId || !state.roomCode) {
    alert("⚠️ Error: Join a room first.");
    return;
  }
  const boardReady = state.board.flat().every((cell) => Number.isInteger(cell));
  if (!boardReady) {
    alert("⚠️ Error: Fill all cells on your board before locking.");
    return;
  }
  fetch(`${BASE_URL}/bingo/room/board`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      roomCode: state.roomCode,
      playerId: state.playerId,
      board: state.board,
    }),
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }
      return res.json();
    })
    .then((data) => {
      if (!data.ok) {
        let errorMsg = data.error || "Unknown error";
        // Add helpful suggestions
        if (errorMsg === "Game already started.") {
          errorMsg += "\n\nYou cannot change your board after the game has started.";
        } else if (errorMsg === "Room not found.") {
          errorMsg += "\n\nThe room no longer exists.";
        } else if (errorMsg === "Player not found.") {
          errorMsg += "\n\nYou are not in this room.";
        }
        alert(`⚠️ Lock Board Error:\n\n${errorMsg}`);
        console.error("Lock board error:", data);
        return;
      }
      applyState(data.state);
    })
    .catch((err) => {
      alert(`⚠️ Lock Board Failed:\n\n${err.message}\n\nCheck console for details.`);
      console.error("Lock board exception:", err);
    });
}

function startPolling() {
  if (state.pollTimer) {
    clearInterval(state.pollTimer);
  }
  state.pollTimer = setInterval(() => {
    if (!state.roomCode) {
      return;
    }
    fetch(`${BASE_URL}/bingo/room/state?roomCode=${encodeURIComponent(state.roomCode)}&playerId=${encodeURIComponent(state.playerId)}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.ok) {
          applyState(data.state);
        }
      })
      .catch(() => {});
  }, 1500);
}

function applyState(nextState) {
  state.roomCode = nextState.roomCode || state.roomCode;
  state.players = nextState.players || [];
  state.calledNumbers = nextState.calledNumbers || [];
  state.turnIndex = nextState.turnIndex ?? 0;
  state.startIndex = nextState.startIndex ?? 0;
  state.winnerId = nextState.winnerId || null;
  state.lastCall = nextState.lastCall || null;
  state.started = !!nextState.started;

  if (nextState.boardSize && nextState.boardSize !== state.boardSize) {
    setBoardSize(nextState.boardSize, true);
  }

  if (nextState.maxNumber && Number.isInteger(nextState.maxNumber)) {
    state.maxNumber = nextState.maxNumber;
  }

  if (nextState.board) {
    const allowServerBoard = !state.boardDirty;
    if (allowServerBoard) {
      state.board = nextState.board;
      state.boardDirty = false;
    }
  }

  renderBoard();
  renderPicker();
  renderCallGrid();
  renderStatus();
  renderLeaderboard();
  updateLockButton();
  updateBoardActions();
}

function renderStatus() {
  // Defensive checks for required elements
  if (!elements.roomStatus || !elements.turnStatus || !elements.linesStatus || !elements.playerList) {
    console.warn("Some status elements not found:", { 
      roomStatus: elements.roomStatus, 
      turnStatus: elements.turnStatus, 
      linesStatus: elements.linesStatus, 
      playerList: elements.playerList 
    });
    return;
  }
  
  if (!state.roomCode) {
    elements.roomStatus.textContent = "Not connected";
  } else {
    elements.roomStatus.textContent = `Room ${state.roomCode}`;
  }

  const playerIndex = state.players.findIndex((p) => p.id === state.playerId);
  const isMyTurn = state.started && playerIndex === state.turnIndex;
  const currentPlayer = state.players[state.turnIndex];
  const drawPlayers = state.players.filter((player) => (player.lines || 0) >= 5);
  const isDraw = !state.winnerId && state.started && drawPlayers.length >= 2;
  const isGameOver = !!state.winnerId || isDraw;

  if (state.winnerId) {
    const winner = state.players.find((p) => p.id === state.winnerId);
    elements.turnStatus.textContent = winner ? `${winner.name} wins!` : "Game over.";
    if (elements.newGame) {
      elements.newGame.textContent = "Start next game";
      elements.newGame.disabled = false;
    }
    // Only show modal if it hasn't been shown for this game
    if (winner && elements.winnerModal && elements.winnerModal.dataset.shown === undefined) {
      showWinnerCelebration(winner.name);
    }
  } else if (isDraw) {
    const drawNames = drawPlayers.map((player) => player.name).join(" & ");
    elements.turnStatus.textContent = `Draw! ${drawNames} reached 5 lines.`;
    if (elements.newGame) {
      elements.newGame.textContent = "Start next game";
      elements.newGame.disabled = false;
    }
  } else if (!allReady()) {
    elements.turnStatus.textContent = "Waiting for all players to be ready.";
    if (elements.newGame) {
      elements.newGame.textContent = "Start game";
      elements.newGame.disabled = true;
    }
  } else if (!state.started) {
    elements.turnStatus.textContent = "Ready to start the game.";
    if (elements.newGame) {
      elements.newGame.textContent = "Start game";
      elements.newGame.disabled = false;
    }
  } else if (currentPlayer) {
    elements.turnStatus.textContent = isMyTurn ? "Your turn to call." : `${currentPlayer.name} is calling.`;
    if (elements.newGame) {
      elements.newGame.textContent = "Start next game";
      elements.newGame.disabled = true;
    }
  }

  const myLines = playerIndex !== -1 ? state.players[playerIndex]?.lines || 0 : 0;
  elements.linesStatus.textContent = String(myLines);
  if (elements.lineLetters) {
    elements.lineLetters.textContent = buildLineLetters(myLines);
  }

  if (elements.lastCall && elements.callMeta) {
    if (state.lastCall) {
      elements.lastCall.textContent = String(state.lastCall.number);
      const by = state.players.find((p) => p.id === state.lastCall.by);
      elements.callMeta.textContent = by ? `Called by ${by.name}` : "";
    } else {
      elements.lastCall.textContent = "None";
      elements.callMeta.textContent = "Waiting for first call.";
    }
  }

  elements.playerList.innerHTML = "";
  state.players.forEach((player, idx) => {
    const row = document.createElement("div");
    row.className = "player-row";
    const label = document.createElement("span");
    const turnMarker = idx === state.turnIndex ? " • turn" : "";
    const readyMarker = player.ready ? "ready" : "not ready";
    label.textContent = `${player.name}${turnMarker}`;
    const meta = document.createElement("span");
    const winsText = player.wins > 0 ? ` • ${player.wins} win${player.wins !== 1 ? "s" : ""}` : "";
    meta.textContent = `${readyMarker}${winsText}`;
    row.appendChild(label);
    row.appendChild(meta);
    elements.playerList.appendChild(row);
  });

  const callButtons = elements.callGrid.querySelectorAll("button");
  callButtons.forEach((button) => {
    const number = Number(button.textContent);
    const alreadyCalled = state.calledNumbers.includes(number);
    button.disabled = alreadyCalled || !isMyTurn || !allReady() || !state.started || isGameOver;
    button.classList.toggle("called", alreadyCalled);
  });

  renderLeaderboard();
}

function renderLeaderboard() {
  if (!elements.leaderboard) {
    console.warn("Leaderboard element not found");
    return;
  }
  elements.leaderboard.innerHTML = "";
  const sorted = [...state.players].sort((a, b) => (b.wins || 0) - (a.wins || 0));
  sorted.forEach((player, idx) => {
    const row = document.createElement("div");
    row.className = "leaderboard-row";
    if (player.id === state.playerId) {
      row.classList.add("current-player");
    }
    const medal = ["🥇", "🥈", "🥉"][idx] || "•";
    const name = document.createElement("span");
    name.textContent = `${medal} ${player.name}`;
    const wins = document.createElement("span");
    wins.textContent = `${player.wins || 0} win${(player.wins || 0) !== 1 ? "s" : ""}`;
    row.appendChild(name);
    row.appendChild(wins);
    elements.leaderboard.appendChild(row);
  });
}

function callNumber(number) {
  if (!state.roomCode || !state.playerId) {
    return;
  }
  if (!state.started) {
    alert("⚠️ Start the game first.");
    return;
  }
  fetch(`${BASE_URL}/bingo/room/call`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      roomCode: state.roomCode,
      playerId: state.playerId,
      number,
    }),
  })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      return res.json();
    })
    .then((data) => {
      if (!data.ok) {
        let errorMsg = data.error || "Unknown error";
        // Add helpful suggestions
        if (errorMsg === "Not your turn.") {
          errorMsg += "\n\nWait for your turn to call.";
        } else if (errorMsg === "Number already called.") {
          errorMsg += "\n\nPick a different number.";
        } else if (errorMsg === "Game is over.") {
          errorMsg += "\n\nStart a new game.";
        } else if (errorMsg === "Waiting for all players to be ready.") {
          errorMsg += "\n\nWait for all players to lock their boards.";
        }
        alert(`⚠️ Error:\n\n${errorMsg}`);
        console.error("Call number error:", data);
        return;
      }
      applyState(data.state);
    })
    .catch((err) => {
      alert(`⚠️ Call failed:\n\n${err.message}`);
      console.error("Call error:", err);
    });
}

function startNextGame() {
  if (!state.roomCode || !state.playerId) {
    return;
  }
  fetch(`${BASE_URL}/bingo/room/new`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      roomCode: state.roomCode,
      playerId: state.playerId,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) {
        alert(data.error || "New game failed.");
        return;
      }
      state.board = createEmptyBoard(state.boardSize);
      state.selectedCell = null;
      state.inputBuffer = "";
      state.boardDirty = false;
      // Reset modal for new game
      if (elements.winnerModal) {
        delete elements.winnerModal.dataset.shown;
        elements.winnerModal.style.display = "none";
      }
      applyState(data.state);
      renderBoard();
      renderPicker();
      updateLockButton();
    })
    .catch(() => alert("New game failed."));
}

function startGame() {
  if (!state.roomCode || !state.playerId) {
    return;
  }
  fetch(`${BASE_URL}/bingo/room/start`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      roomCode: state.roomCode,
      playerId: state.playerId,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) {
        alert(data.error || "Start game failed.");
        return;
      }
      applyState(data.state);
    })
    .catch(() => alert("Start game failed."));
}

function handleGameButton() {
  if (!allReady()) {
    return;
  }
  if (!state.started) {
    startGame();
    return;
  }
  if (state.winnerId) {
    startNextGame();
  }
}

function buildLineLetters(lines) {
  if (lines <= 0) {
    return "Need 5 lines to win.";
  }
  const letters = LINE_LETTERS.slice(0, Math.min(lines, LINE_LETTERS.length));
  const text = letters.join(" ");
  if (lines >= 5) {
    return `${text} - Bingo!`;
  }
  return text;
}

function allReady() {
  return state.players.length > 0 && state.players.every((player) => player.ready);
}

function boardHasNumber(number, skipCell) {
  for (let r = 0; r < state.board.length; r++) {
    for (let c = 0; c < state.board[r].length; c++) {
      if (skipCell && skipCell[0] === r && skipCell[1] === c) {
        continue;
      }
      if (state.board[r][c] === number) {
        return true;
      }
    }
  }
  return false;
}

function isMarked(number) {
  if (!number) {
    return false;
  }
  return state.calledNumbers.includes(number);
}

function shuffleArray(array) {
  const copy = [...array];
  for (let i = copy.length - 1; i > 0; i--) {
    const j = getRandomInt(i + 1);
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}

function getRandomInt(maxExclusive) {
  if (maxExclusive <= 0) {
    return 0;
  }
  const cryptoObj = typeof window !== "undefined" ? window.crypto || window.msCrypto : null;
  if (cryptoObj && cryptoObj.getRandomValues) {
    const limit = Math.floor(0x100000000 / maxExclusive) * maxExclusive;
    const buffer = new Uint32Array(1);
    let value = 0;
    do {
      cryptoObj.getRandomValues(buffer);
      value = buffer[0];
    } while (value >= limit);
    return value % maxExclusive;
  }
  return Math.floor(Math.random() * maxExclusive);
}

function getCompletedLines() {
  const lines = [];
  const size = state.boardSize;
  const called = new Set(state.calledNumbers);

  for (let r = 0; r < size; r++) {
    let rowComplete = true;
    for (let c = 0; c < size; c++) {
      if (!called.has(state.board[r][c])) {
        rowComplete = false;
        break;
      }
    }
    if (rowComplete) {
      lines.push({ type: "row", index: r });
    }
  }

  for (let c = 0; c < size; c++) {
    let colComplete = true;
    for (let r = 0; r < size; r++) {
      if (!called.has(state.board[r][c])) {
        colComplete = false;
        break;
      }
    }
    if (colComplete) {
      lines.push({ type: "col", index: c });
    }
  }

  let diag1Complete = true;
  for (let i = 0; i < size; i++) {
    if (!called.has(state.board[i][i])) {
      diag1Complete = false;
      break;
    }
  }
  if (diag1Complete) {
    lines.push({ type: "diag", index: 0 });
  }

  let diag2Complete = true;
  for (let i = 0; i < size; i++) {
    if (!called.has(state.board[i][size - 1 - i])) {
      diag2Complete = false;
      break;
    }
  }
  if (diag2Complete) {
    lines.push({ type: "diag", index: 1 });
  }

  return lines;
}

function isInCompletedLine(r, c) {
  const lines = getCompletedLines();
  for (const line of lines) {
    if (line.type === "row" && line.index === r) return "row";
    if (line.type === "col" && line.index === c) return "col";
    if (line.type === "diag" && line.index === 0 && r === c) return "diag";
    if (line.type === "diag" && line.index === 1 && r + c === state.boardSize - 1) return "diag";
  }
  return null;
}

function showWinnerCelebration(name) {
  if (!elements.winnerModal || !elements.winnerName) return;
  elements.winnerName.textContent = name;
  elements.winnerModal.dataset.shown = "true";
  elements.winnerModal.style.display = "flex";
  playConfetti();
}

function closeWinnerModal() {
  if (!elements.winnerModal) return;
  elements.winnerModal.style.display = "none";
  // Mark modal as explicitly closed for this game
  elements.winnerModal.dataset.shown = "closed";
  renderStatus();
  updateLockButton();
}

function playConfetti() {
  const confetti = document.querySelector(".confetti");
  if (!confetti) return;
  confetti.innerHTML = "";
  for (let i = 0; i < 50; i++) {
    const piece = document.createElement("div");
    piece.className = "confetti-piece";
    piece.style.left = Math.random() * 100 + "%";
    piece.style.animationDelay = Math.random() * 0.5 + "s";
    confetti.appendChild(piece);
  }
}
