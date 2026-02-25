# Debug Logs - Bingo Victory Popup Testing

## How to Check Console Logs During Gameplay

### Step 1: Open Developer Tools
- **Chrome/Edge**: Press `F12` or `Ctrl + Shift + I` (Windows) / `Cmd + Option + I` (Mac)
- **Firefox**: Press `F12` or `Ctrl + Shift + K`
- **Safari**: Press `Cmd + Option + C` (enable Developer menu first in Preferences)

### Step 2: Navigate to Console Tab
1. Click the **Console** tab in Developer Tools
2. Make sure the console is clear - click the 🚫 icon to clear if needed

### Step 3: Play the Game
1. Create or join a room
2. Lock your board
3. Play until someone reaches 5 lines (wins)

### Step 4: Look for Victory Logs

When someone wins, you should see these logs:

#### ✅ **Expected Logs (Victory Working)**
```
Game over check: { winnerCount: 1, hasSeenVictory: false, modalExists: true, isPlayerWinner: true/false, winnerIds: [...] }
🎉 Triggering victory modal for winner (or spectator)
🎯 Setting modal visible: { currentDisplay: "none" }
✅ Modal display set to flex with zIndex 99999 { newDisplay: "flex" }
```

#### ❌ **Problem Indicators**

**If you see:**
```
Game over check: { ... hasSeenVictory: true ... }
Victory already shown to this player
```
→ **Issue**: Modal showed before but was closed, not showing again

**If you see:**
```
Game over check: { ... modalExists: false ... }
```
→ **Issue**: Modal HTML element missing from page

**If you see:**
```
❌ winnerModal element not found!
```
→ **Issue**: Modal element doesn't exist in DOM

**If you don't see "Game over check" at all:**
→ **Issue**: Victory detection not triggering (backend or state problem)

### Step 5: Check Modal Element

If logs show modal is being set to `display: flex` but you don't see it:

1. In Developer Tools, click **Elements** tab (or **Inspector**)
2. Press `Ctrl + F` and search for `winnerModal`
3. Click on the `<div id="winnerModal">` element
4. In the **Styles** panel on the right, check:
   - `display: flex` should be present
   - `z-index: 999999` should be present
   - No `display: none !important` overriding it

### Step 6: Test Modal Manually

In the **Console** tab, type:
```javascript
document.getElementById('winnerModal').style.display = 'flex'
```
Press Enter. If modal appears → CSS is fine, trigger logic is broken.
If modal doesn't appear → CSS/DOM issue.

## Common Issues & Solutions

### Modal Shows But Immediately Disappears
- Check for JavaScript errors right after victory
- Look for any code hiding the modal

### Modal Never Shows
- Verify `state.hasSeenVictory` resets to `false` on new game
- Check if polling is working (should see state updates in logs)

### Modal Shows for Winner Only
- Old code had `isPlayerWinner` check - verify latest code deployed

## Report Issues

When reporting, include:
1. Full console log output from victory moment
2. Screenshot of Elements → #winnerModal styles
3. Which player you were (winner or spectator)
4. Whether manual modal test worked

---

**Last Updated**: February 2026
