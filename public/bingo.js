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
  pollTimer: null,
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
  document.addEventListener("keydown", handleKeyInput);
}

function createEmptyBoard(size) {
  return Array.from({ length: size }, () => Array.from({ length: size }, () => null));
}

function renderBoard() {
  elements.board.innerHTML = "";
  elements.board.style.setProperty("--board-size", state.boardSize);
  elements.board.dataset.size = String(state.boardSize);
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
      if (state.selectedCell && state.selectedCell[0] === r && state.selectedCell[1] === c && state.inputBuffer) {
        div.textContent = state.inputBuffer;
      } else {
        div.textContent = cell ? String(cell) : "";
      }
      div.addEventListener("click", () => handleCellClick(r, c));
      div.addEventListener("dblclick", () => handleCellCall(r, c));
      elements.board.appendChild(div);
    });
  });
}

function renderPicker() {
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
  renderBoard();
  renderPicker();
  updateLockButton();
}

function clearBoard() {
  state.board = createEmptyBoard(state.boardSize);
  state.selectedCell = null;
  state.inputBuffer = "";
  renderBoard();
  renderPicker();
  updateLockButton();
}

function updateLockButton() {
  const full = state.board.flat().every((cell) => Number.isInteger(cell));
  elements.lockBoard.disabled = !full || !state.playerId || state.calledNumbers.length > 0;
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
    alert("Enter your name and room code.");
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
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) {
        alert(data.error || "Room action failed.");
        return;
      }
      state.playerId = data.playerId;
      localStorage.setItem("bingo.playerId", state.playerId);
      applyState(data.state);
      startPolling();
    })
    .catch(() => alert("Room action failed."));
}

function lockBoard() {
  if (!state.playerId || !state.roomCode) {
    alert("Join a room first.");
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
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) {
        alert(data.error || "Board update failed.");
        return;
      }
      applyState(data.state);
    })
    .catch(() => alert("Board update failed."));
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
    state.board = nextState.board;
  }

  renderBoard();
  renderPicker();
  renderCallGrid();
  renderStatus();
  updateLockButton();
}

function renderStatus() {
  if (!state.roomCode) {
    elements.roomStatus.textContent = "Not connected";
  } else {
    elements.roomStatus.textContent = `Room ${state.roomCode}`;
  }

  const playerIndex = state.players.findIndex((p) => p.id === state.playerId);
  const isMyTurn = state.started && playerIndex === state.turnIndex;
  const currentPlayer = state.players[state.turnIndex];

  if (state.winnerId) {
    const winner = state.players.find((p) => p.id === state.winnerId);
    elements.turnStatus.textContent = winner ? `${winner.name} wins!` : "Game over.";
    elements.newGame.textContent = "Start next game";
    elements.newGame.disabled = false;
  } else if (!allReady()) {
    elements.turnStatus.textContent = "Waiting for all players to be ready.";
    elements.newGame.textContent = "Start game";
    elements.newGame.disabled = true;
  } else if (!state.started) {
    elements.turnStatus.textContent = "Ready to start the game.";
    elements.newGame.textContent = "Start game";
    elements.newGame.disabled = false;
  } else if (currentPlayer) {
    elements.turnStatus.textContent = isMyTurn ? "Your turn to call." : `${currentPlayer.name} is calling.`;
    elements.newGame.textContent = "Start next game";
    elements.newGame.disabled = true;
  }

  const myLines = playerIndex !== -1 ? state.players[playerIndex]?.lines || 0 : 0;
  elements.linesStatus.textContent = String(myLines);
  elements.lineLetters.textContent = buildLineLetters(myLines);

  if (state.lastCall) {
    elements.lastCall.textContent = String(state.lastCall.number);
    const by = state.players.find((p) => p.id === state.lastCall.by);
    elements.callMeta.textContent = by ? `Called by ${by.name}` : "";
  } else {
    elements.lastCall.textContent = "None";
    elements.callMeta.textContent = "Waiting for first call.";
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
    meta.textContent = `${player.lines} lines • ${readyMarker}`;
    row.appendChild(label);
    row.appendChild(meta);
    elements.playerList.appendChild(row);
  });

  const callButtons = elements.callGrid.querySelectorAll("button");
  callButtons.forEach((button) => {
    const number = Number(button.textContent);
    const alreadyCalled = state.calledNumbers.includes(number);
    button.disabled = alreadyCalled || !isMyTurn || !allReady() || !state.started || !!state.winnerId;
    button.classList.toggle("called", alreadyCalled);
  });
}

function callNumber(number) {
  if (!state.roomCode || !state.playerId) {
    return;
  }
  if (!state.started) {
    alert("Start the game first.");
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
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) {
        alert(data.error || "Call failed.");
        return;
      }
      applyState(data.state);
    })
    .catch(() => alert("Call failed."));
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
      applyState(data.state);
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
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}
