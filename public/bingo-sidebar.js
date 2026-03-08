// Sidebar and Turn Indicator Extensions for Bingo Game

// Override player list rendering to add turn indicator
(function() {
  const originalRenderState = window.renderState;
  
  // Add sidebar init and toggle functions
  window.initSidebar = function() {
    const elements = {
      sidebar: document.getElementById("sidebar"),
      sidebarToggle: document.getElementById("sidebarToggle")
    };
    
    const sidebarState = sessionStorage.getItem("bingo.sidebarCollapsed");
    if (sidebarState === "true" && elements.sidebar) {
      elements.sidebar.classList.add("collapsed");
      if (elements.sidebarToggle) {
        elements.sidebarToggle.setAttribute("aria-expanded", "false");
      }
    }
  };
  
  window.toggleSidebar = function() {
    const sidebar = document.getElementById("sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    
    if (!sidebar || !sidebarToggle) return;
    
    const isCollapsed = sidebar.classList.toggle("collapsed");
    
    // Save state to session storage
    sessionStorage.setItem("bingo.sidebarCollapsed", isCollapsed.toString());
    
    // Update aria-expanded for accessibility
    sidebarToggle.setAttribute("aria-expanded", (!isCollapsed).toString());
  };
  
  // Enhance player rows with active turn indicator
  const originalPlayerListRender = document.getElementById("playerList");
  if (originalPlayerListRender) {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          // Add active turn class to current player
          const playerRows = document.querySelectorAll('.player-row');
          playerRows.forEach((row, idx) => {
            const turnText = row.textContent;
            if (turnText.includes('• turn')) {
              row.classList.add('active-turn');
            } else {
              row.classList.remove('active-turn');
            }
          });
        }
      });
    });
    
    observer.observe(originalPlayerListRender, { childList: true, subtree: true });
  }
})();
