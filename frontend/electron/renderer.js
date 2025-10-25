/**
 * Renderer Process - Connects UI to Native Engines via IPC
 */

const { ipcRenderer } = require('electron');

// State
let tabs = [];
let activeTabId = null;
let tabIdCounter = 0;
let defaultEngine = 'firefox';

// DOM Elements
const tabBar = document.getElementById('tabBar');
const addressBar = document.getElementById('addressBar');
const startPage = document.getElementById('startPage');
const startSearch = document.getElementById('startSearch');
const backBtn = document.getElementById('backBtn');
const forwardBtn = document.getElementById('forwardBtn');
const refreshBtn = document.getElementById('refreshBtn');
const extensionsBtn = document.getElementById('extensionsBtn');
const walletBtn = document.getElementById('walletBtn');
const walletModal = document.getElementById('walletModal');
const closeWallet = document.getElementById('closeWallet');
const engineSelector = document.getElementById('engineSelector');
const engineBadge = document.getElementById('engineBadge');

// New Haven Services Elements
const havenPayBtn = document.getElementById('havenPayBtn');
const havenPayModal = document.getElementById('havenPayModal');
const closeHavenPay = document.getElementById('closeHavenPay');
const aiBtn = document.getElementById('aiBtn');

// AI Tab Page Elements
const aiPage = document.getElementById('aiPage');
const aiTabMessages = document.getElementById('aiTabMessages');
const aiTabInput = document.getElementById('aiTabInput');
const aiTabSendBtn = document.getElementById('aiTabSendBtn');
const aiWelcome = document.getElementById('aiWelcome');

// Start Page Search Elements
const searchModeToggle = document.getElementById('searchModeToggle');
const searchModeIndicator = document.getElementById('searchModeIndicator');
const searchHint = document.getElementById('searchHint');

// State
let isAIMode = false;
let currentAITabId = null;

// Initialize
async function init() {
    setupEventListeners();
    updateEngineBadge();
    loadWallets();
    
    // Wait for engines to be ready before creating first tab
    console.log('⏳ Waiting for engines to initialize...');
}

// Listen for engines ready event
ipcRenderer.once('engines-ready', async () => {
    console.log('✅ Engines ready');
    // Create an initial empty tab and show start page
    await createNewTab();
});

// Event Listeners
function setupEventListeners() {
    // Address bar
    addressBar.addEventListener('keypress', async (e) => {
        if (e.key === 'Enter') {
            await navigateTo(addressBar.value);
        }
    });

    // Toggle AI mode button
    searchModeToggle.addEventListener('click', () => {
        isAIMode = !isAIMode;
        
        if (isAIMode) {
            searchModeToggle.classList.add('ai-mode');
            searchModeIndicator.innerHTML = Icons.sparkles;
            document.querySelector('.search-input-wrapper').classList.add('ai-mode');
            startSearch.placeholder = 'Ask AI anything...';
            searchHint.textContent = 'AI Mode Active';
            searchHint.style.background = 'rgba(0, 122, 255, 0.1)';
            searchHint.style.color = '#007AFF';
        } else {
            searchModeToggle.classList.remove('ai-mode');
            searchModeIndicator.innerHTML = Icons.search;
            document.querySelector('.search-input-wrapper').classList.remove('ai-mode');
            startSearch.placeholder = 'Search the web...';
            searchHint.innerHTML = `Click ${Icons.search} to toggle AI mode`;
            searchHint.style.background = 'rgba(0, 122, 255, 0.05)';
            searchHint.style.color = '#86868b';
        }
        
        // Focus input after toggle
        startSearch.focus();
    });

    // Start search - handle Enter key
    startSearch.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = startSearch.value.trim();
            if (!query) return;
            
            console.log('Start search enter pressed:', query, 'AI mode:', isAIMode);
            
            if (isAIMode) {
                // Open AI in a new tab with context
                await openAITab(query);
                startSearch.value = '';
                // Reset AI mode after search
                isAIMode = false;
                searchModeToggle.classList.remove('ai-mode');
                searchModeIndicator.innerHTML = Icons.search;
                document.querySelector('.search-input-wrapper').classList.remove('ai-mode');
                startSearch.placeholder = 'Search the web...';
                searchHint.innerHTML = `Click ${Icons.search} to toggle AI mode`;
                searchHint.style.background = 'rgba(0, 122, 255, 0.05)';
                searchHint.style.color = '#86868b';
            } else {
                // Make sure we have a tab to navigate
                if (tabs.length === 0 || !tabs.find(t => t.id === activeTabId)) {
                    console.log('No active tab, creating one first');
                    await createNewTab();
                }
                // Navigate to search
                await navigateTo(query);
                startSearch.value = '';
            }
        }
    });

    // Navigation buttons
    backBtn.addEventListener('click', async () => {
        const tab = tabs.find(t => t.id === activeTabId);
        if (tab) {
            await ipcRenderer.invoke('engine:goBack', tab.id);
        }
    });

    forwardBtn.addEventListener('click', async () => {
        const tab = tabs.find(t => t.id === activeTabId);
        if (tab) {
            await ipcRenderer.invoke('engine:goForward', tab.id);
        }
    });

    refreshBtn.addEventListener('click', async () => {
        const tab = tabs.find(t => t.id === activeTabId);
        if (tab && tab.url) {
            await ipcRenderer.invoke('engine:reload', tab.id);
        } else {
            showStartPage();
        }
    });

    // Engine selector
    engineSelector.addEventListener('change', async (e) => {
        const newEngine = e.target.value;
        const tab = tabs.find(t => t.id === activeTabId);
        
        if (tab) {
            // Warn user when switching to Tor
            if (newEngine === 'tor') {
                const confirmed = confirm(
                    '🔒 Tor Anonymous Browsing\n\n' +
                    'This tab will connect to the Tor network with:\n' +
                    '• Anonymous routing through Tor relays\n' +
                    '• New circuit for this tab only\n' +
                    '• Maximum privacy protections\n' +
                    '• Slower speeds due to routing\n\n' +
                    'Note: Tor service must be installed and running.\n\n' +
                    'Continue with Tor?'
                );
                
                if (!confirmed) {
                    // Revert selector to current engine
                    engineSelector.value = tab.engine;
                    return;
                }
            }
            
            try {
                // Switch engine for this tab
                await ipcRenderer.invoke('engine:switchTabEngine', tab.id, newEngine);
                tab.engine = newEngine;
                updateEngineBadge();
                renderTabs();
                console.log(`Tab "${tab.title}" switched to: ${newEngine}`);
            } catch (error) {
                alert(`Failed to switch to ${newEngine}: ${error.message}`);
                // Revert selector
                engineSelector.value = tab.engine;
            }
        }
    });

    // Extensions
    extensionsBtn.addEventListener('click', async () => {
        // Open extensions page as a special tab
        await openExtensionsPage();
    });

    // Extensions close button
    const extensionsCloseBtn = document.getElementById('extensionsCloseBtn');
    if (extensionsCloseBtn) {
        extensionsCloseBtn.addEventListener('click', () => {
            const extensionsTab = tabs.find(t => t.isExtensionsTab && t.id === activeTabId);
            if (extensionsTab) {
                closeTab(extensionsTab.id);
            }
        });
    }

    // Extensions tabs switching
    document.querySelectorAll('.extensions-tab').forEach(tabBtn => {
        tabBtn.addEventListener('click', async () => {
            // Remove active from all tabs
            document.querySelectorAll('.extensions-tab').forEach(btn => btn.classList.remove('active'));
            tabBtn.classList.add('active');
            
            const tabType = tabBtn.dataset.tab;
            if (tabType === 'featured') {
                await loadFeaturedExtensions();
            } else if (tabType === 'installed') {
                await loadInstalledExtensions();
            }
        });
    });

    // HavenWallet modal
    walletBtn.addEventListener('click', async () => {
        // Hide BrowserViews so modal is on top
        try {
            await ipcRenderer.invoke('engine:hideAllViews');
        } catch (error) {
            console.error('Failed to hide views:', error);
        }
        walletModal.classList.add('show');
    });

    closeWallet.addEventListener('click', async () => {
        walletModal.classList.remove('show');
        // Show active BrowserView again
        try {
            await ipcRenderer.invoke('engine:showActiveView');
        } catch (error) {
            console.error('Failed to show views:', error);
        }
    });

    walletModal.addEventListener('click', async (e) => {
        if (e.target === walletModal) {
            walletModal.classList.remove('show');
            // Show active BrowserView again
            try {
                await ipcRenderer.invoke('engine:showActiveView');
            } catch (error) {
                console.error('Failed to show views:', error);
            }
        }
    });

    // HavenPay modal
    havenPayBtn.addEventListener('click', async () => {
        // Hide BrowserViews so modal is on top
        try {
            await ipcRenderer.invoke('engine:hideAllViews');
        } catch (error) {
            console.error('Failed to hide views:', error);
        }
        havenPayModal.classList.add('show');
    });

    closeHavenPay.addEventListener('click', async () => {
        havenPayModal.classList.remove('show');
        // Show active BrowserView again
        try {
            await ipcRenderer.invoke('engine:showActiveView');
        } catch (error) {
            console.error('Failed to show views:', error);
        }
    });

    havenPayModal.addEventListener('click', async (e) => {
        if (e.target === havenPayModal) {
            havenPayModal.classList.remove('show');
            // Show active BrowserView again
            try {
                await ipcRenderer.invoke('engine:showActiveView');
            } catch (error) {
                console.error('Failed to show views:', error);
            }
        }
    });

    // AI Assistant button - opens AI in new tab
    aiBtn.addEventListener('click', async () => {
        await openAITab();
    });

    // AI Tab Input handlers
    aiTabSendBtn.addEventListener('click', async () => {
        const query = aiTabInput.value.trim();
        if (query) {
            await sendAIMessage(query);
            aiTabInput.value = '';
        }
    });
    
    // AI Close button
    const aiCloseBtn = document.getElementById('aiCloseBtn');
    aiCloseBtn.addEventListener('click', () => {
        // Find and close the AI tab
        const aiTab = tabs.find(t => t.isAITab && t.id === activeTabId);
        if (aiTab) {
            closeTab(aiTab.id);
        }
    });

    aiTabInput.addEventListener('keypress', async (e) => {
        if (e.key === 'Enter') {
            const query = aiTabInput.value.trim();
            if (query) {
                await sendAIMessage(query);
                aiTabInput.value = '';
            }
        }
    });

    // AI Suggestion cards
    document.querySelectorAll('.ai-suggestion-card').forEach(card => {
        card.addEventListener('click', async () => {
            const prompt = card.getAttribute('data-prompt');
            aiTabInput.value = prompt;
            await sendAIMessage(prompt);
            aiTabInput.value = '';
        });
    });

    // Quick Links - need to wait for DOM to be ready
    setupQuickLinks();
}

function setupQuickLinks() {
    const quickLinks = document.querySelectorAll('.quick-link');
    console.log('Setting up quick links:', quickLinks.length);
    
    quickLinks.forEach(item => {
        // Remove any existing listeners
        const newItem = item.cloneNode(true);
        item.parentNode.replaceChild(newItem, item);
        
        newItem.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const url = newItem.getAttribute('data-url');
            console.log('Quick link clicked:', url, 'Current tabs:', tabs.length, 'Active:', activeTabId);
            
            if (url) {
                try {
                    // Make sure we have a tab to navigate
                    if (tabs.length === 0 || !tabs.find(t => t.id === activeTabId)) {
                        console.log('No active tab for quick link, creating one');
                        await createNewTab();
                    }
                    await navigateTo(url);
                } catch (error) {
                    console.error('Quick link navigation error:', error);
                }
            }
        });
    });

    // Haven Service Cards - Removed from home page but keep modal functionality

    // Listen for engine events
    ipcRenderer.on('engine-event', (event, { event: eventName, data }) => {
        handleEngineEvent(eventName, data);
    });

    // Menu shortcuts
    ipcRenderer.on('new-tab', async () => await createNewTab());
    ipcRenderer.on('close-tab', async () => await closeTab(activeTabId));
    ipcRenderer.on('navigate-back', () => backBtn.click());
    ipcRenderer.on('navigate-forward', () => forwardBtn.click());
    ipcRenderer.on('navigate-refresh', () => refreshBtn.click());
    ipcRenderer.on('navigate-home', () => showStartPage());
}

// Tab Management
async function createNewTab() {
    const tabId = `tab-${tabIdCounter++}`;
    const tab = {
        id: tabId,
        title: 'New Tab',
        url: '',
        engine: defaultEngine,
        isLoading: false
    };

    tabs.push(tab);
    console.log('Creating new tab:', tabId);
    
    // Set as active tab
    activeTabId = tabId;
    
    // Update UI immediately
    renderTabs();
    updateEngineBadge();
    
    // Create tab in native engine in the background (don't switch to it yet)
    try {
        await ipcRenderer.invoke('engine:createTab', tabId, defaultEngine, {});
        console.log(`✅ Native tab ${tabId} created with ${defaultEngine}`);
        
        // DON'T call switchToTab here - let the user navigate first
        // The BrowserView will be shown when they navigate somewhere
        
        // Show the start page for the new tab
        await showStartPage();
    } catch (error) {
        console.error('Failed to create tab:', error);
        // Remove tab if creation failed
        const index = tabs.findIndex(t => t.id === tabId);
        if (index > -1) {
            tabs.splice(index, 1);
            renderTabs();
            // Switch to another tab if available
            if (tabs.length > 0) {
                await switchToTab(tabs[tabs.length - 1].id);
            } else {
                activeTabId = null;
                await showStartPage();
            }
        }
    }
}

async function closeTab(tabId) {
    const index = tabs.findIndex(t => t.id === tabId);
    if (index === -1) return;

    const tab = tabs[index];
    
    // Close tab in native engine (skip for AI tabs and extensions tabs)
    if (!tab.isAITab && !tab.isExtensionsTab) {
        try {
            await ipcRenderer.invoke('engine:closeTab', tab.id);
        } catch (error) {
            console.error('Failed to close tab:', error);
        }
    } else if (tab.isAITab) {
        // Hide AI page if closing AI tab
        if (tabId === currentAITabId) {
            aiPage.style.display = 'none';
            currentAITabId = null;
        }
    } else if (tab.isExtensionsTab) {
        // Hide extensions page if closing extensions tab
        const extensionsPage = document.getElementById('extensionsPage');
        if (extensionsPage) {
            extensionsPage.style.display = 'none';
        }
    }

    tabs.splice(index, 1);

    if (tabs.length === 0) {
        // Show start page instead of creating a new tab
        showStartPage();
        activeTabId = null;
        renderTabs();
    } else if (activeTabId === tabId) {
        const newActiveIndex = Math.min(index, tabs.length - 1);
        await switchToTab(tabs[newActiveIndex].id);
        renderTabs();
    } else {
        renderTabs();
    }
}

async function switchToTab(tabId) {
    const oldActiveTabId = activeTabId;
    activeTabId = tabId;
    const tab = tabs.find(t => t.id === tabId);
    if (!tab) return;

    const extensionsPage = document.getElementById('extensionsPage');

    // Check if it's an AI tab
    if (tab.isAITab) {
        // Show AI page
        aiPage.style.display = 'flex';
        startPage.classList.add('hidden');
        if (extensionsPage) extensionsPage.style.display = 'none';
        addressBar.value = 'prism://ai';
        updateEngineBadge();
        renderTabs();
        
        // Hide BrowserViews
        try {
            await ipcRenderer.invoke('engine:hideAllViews');
        } catch (error) {
            console.error('Failed to hide views:', error);
        }
        return;
    } else {
        // Hide AI page
        aiPage.style.display = 'none';
    }

    // Check if it's an extensions tab
    if (tab.isExtensionsTab) {
        // Show extensions page
        if (extensionsPage) extensionsPage.style.display = 'flex';
        startPage.classList.add('hidden');
        aiPage.style.display = 'none';
        addressBar.value = 'prism://extensions';
        updateEngineBadge();
        renderTabs();
        
        // Hide BrowserViews
        try {
            await ipcRenderer.invoke('engine:hideAllViews');
        } catch (error) {
            console.error('Failed to hide views:', error);
        }
        return;
    } else {
        // Hide extensions page
        if (extensionsPage) extensionsPage.style.display = 'none';
    }

    // Update address bar IMMEDIATELY to prevent wrong URL showing
    if (tab.url) {
        addressBar.value = tab.url;
        startPage.classList.add('hidden');
    } else {
        addressBar.value = '';
        showStartPage();
    }

    // Update UI immediately
    updateEngineBadge();
    renderTabs();

    // Show this tab, hide others (async in background)
    try {
        await ipcRenderer.invoke('engine:showTab', tabId);
        await updateNavButtons();
    } catch (error) {
        console.error('Failed to switch tab:', error);
        // Revert if switch failed
        activeTabId = oldActiveTabId;
        const oldTab = tabs.find(t => t.id === oldActiveTabId);
        if (oldTab) {
            addressBar.value = oldTab.url || '';
            updateEngineBadge();
            renderTabs();
        }
    }
}

function renderTabs() {
    tabBar.innerHTML = '';

    tabs.forEach((tab, index) => {
        const tabEl = document.createElement('div');
        tabEl.className = `tab ${tab.id === activeTabId ? 'active' : ''}`;
        tabEl.setAttribute('draggable', 'true');
        tabEl.dataset.tabId = tab.id;
        tabEl.dataset.tabIndex = index;
        
        // Favicon with engine indicator
        const faviconEl = document.createElement('div');
        faviconEl.className = 'tab-favicon';
        
        // Color-coded by engine
        const engineColors = {
            'tor': '#7C3AED',
            'prism': '#34C759',
            'chromium': '#007AFF',
            'firefox': '#FF9500'
        };
        
        faviconEl.textContent = tab.url ? '●' : '○';
        faviconEl.style.color = engineColors[tab.engine] || '#86868b';
        
        const titleEl = document.createElement('div');
        titleEl.className = 'tab-title';
        titleEl.textContent = tab.title;
        
        const closeEl = document.createElement('button');
        closeEl.className = 'tab-close';
        closeEl.textContent = '×';
        closeEl.addEventListener('click', (e) => {
            e.stopPropagation();
            closeTab(tab.id);
        });

        tabEl.appendChild(faviconEl);
        tabEl.appendChild(titleEl);
        tabEl.appendChild(closeEl);
        
        // Click to switch
        tabEl.addEventListener('click', () => {
            switchToTab(tab.id);
        });

        // Drag and drop functionality
        tabEl.addEventListener('dragstart', handleTabDragStart);
        tabEl.addEventListener('dragover', handleTabDragOver);
        tabEl.addEventListener('drop', handleTabDrop);
        tabEl.addEventListener('dragend', handleTabDragEnd);
        tabEl.addEventListener('dragenter', handleTabDragEnter);
        tabEl.addEventListener('dragleave', handleTabDragLeave);

        tabBar.appendChild(tabEl);
    });

    // Add new tab button
    const newTabBtn = document.createElement('button');
    newTabBtn.className = 'tab-new';
    newTabBtn.textContent = '+';
    newTabBtn.addEventListener('click', createNewTab);
    tabBar.appendChild(newTabBtn);
}

// Tab drag and drop handlers
let draggedTab = null;
let draggedTabIndex = null;

function handleTabDragStart(e) {
    draggedTab = e.currentTarget;
    draggedTabIndex = parseInt(draggedTab.dataset.tabIndex);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', draggedTab.innerHTML);
    
    // Add dragging visual feedback
    setTimeout(() => {
        draggedTab.style.opacity = '0.4';
    }, 0);
}

function handleTabDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleTabDragEnter(e) {
    if (e.currentTarget.classList.contains('tab') && e.currentTarget !== draggedTab) {
        e.currentTarget.style.borderLeft = '2px solid #007AFF';
    }
}

function handleTabDragLeave(e) {
    if (e.currentTarget.classList.contains('tab')) {
        e.currentTarget.style.borderLeft = '';
    }
}

function handleTabDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    const dropTarget = e.currentTarget;
    
    if (draggedTab !== dropTarget && dropTarget.classList.contains('tab')) {
        const dropIndex = parseInt(dropTarget.dataset.tabIndex);
        
        // Reorder tabs array
        const draggedTabData = tabs[draggedTabIndex];
        tabs.splice(draggedTabIndex, 1);
        
        // Adjust drop index if needed
        const newIndex = draggedTabIndex < dropIndex ? dropIndex - 1 : dropIndex;
        tabs.splice(newIndex, 0, draggedTabData);
        
        // Re-render tabs
        renderTabs();
    }
    
    // Clear border
    dropTarget.style.borderLeft = '';
    
    return false;
}

function handleTabDragEnd(e) {
    // Reset opacity
    if (draggedTab) {
        draggedTab.style.opacity = '1';
    }
    
    // Clear all drag indicators
    document.querySelectorAll('.tab').forEach(tab => {
        tab.style.borderLeft = '';
    });
    
    draggedTab = null;
    draggedTabIndex = null;
}

// Navigation
async function navigateTo(input) {
    if (!input) return;

    let url = input.trim();
    console.log('Navigate requested to:', url);
    
    // Get current active tab
    let tab = tabs.find(t => t.id === activeTabId);
    
    // ONLY create new tab if we have no tabs at all
    if (!tab) {
        console.log('No tabs exist, creating first tab');
        await createNewTab();
        tab = tabs.find(t => t.id === activeTabId);
        if (!tab) return;
    }
    
    // If on AI tab, don't navigate - it's not a web tab
    if (tab.isAITab) {
        console.log('Cannot navigate in AI tab');
        return;
    }

    // Handle prism:// protocol
    if (url.startsWith('prism://')) {
        await handlePrismProtocol(url);
        return;
    }

    // Use the tab's engine
    const tabEngine = tab.engine || defaultEngine;
    
    // Check if it's a URL or search query
    const isSearch = !url.includes('.') || url.includes(' ');
    
    if (tabEngine === 'prism' && isSearch) {
        await performPrismSearch(url);
        return;
    } else if (isSearch) {
        // Use engine-specific search engines
        if (tabEngine === 'tor') {
            url = `https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/?q=${encodeURIComponent(url)}`;
        } else if (tabEngine === 'firefox') {
            url = `https://duckduckgo.com/?q=${encodeURIComponent(url)}`;
        } else if (tabEngine === 'chromium') {
            url = `https://www.google.com/search?q=${encodeURIComponent(url)}`;
        } else {
            url = `https://www.google.com/search?q=${encodeURIComponent(url)}`;
        }
    } else if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
    }

    console.log('Navigating to:', url, 'in existing tab:', tab.id);

    // Update tab state
    tab.url = url;
    tab.isLoading = true;
    
    // Update address bar
    if (activeTabId === tab.id) {
        addressBar.value = url;
    }

    try {
        await ipcRenderer.invoke('engine:navigate', tab.id, url);
        
        // Hide start page and show the BrowserView
        if (activeTabId === tab.id) {
            startPage.classList.add('hidden');
            // Show the active tab's BrowserView
            await ipcRenderer.invoke('engine:showTab', tab.id);
        }
        
        tab.isLoading = false;
        renderTabs();
    } catch (error) {
        console.error('Navigation failed:', error);
        tab.isLoading = false;
        
        if (activeTabId === tab.id) {
            alert('Failed to navigate: ' + error.message);
        }
        renderTabs();
    }
}

// Prism Search
async function performPrismSearch(query) {
    try {
        const response = await fetch(`http://localhost:8000/api/search?q=${encodeURIComponent(query)}`);
        const result = await response.json();
        
        if (result.success) {
            // TODO: Display results in Prism engine
            console.log('Prism search results:', result);
        } else {
            alert('Search error: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Prism search error:', error);
        // Fallback to Google search if backend is not available
        const tab = tabs.find(t => t.id === activeTabId);
        if (tab) {
            const fallbackUrl = `https://www.google.com/search?q=${encodeURIComponent(query)}`;
            await ipcRenderer.invoke('engine:navigate', tab.id, fallbackUrl);
            tab.url = fallbackUrl;
        }
    }
}

async function handlePrismProtocol(url) {
    const prismUrl = new URL(url);
    const path = prismUrl.hostname;

    if (path === 'search') {
        const query = prismUrl.searchParams.get('q');
        if (query) {
            await performPrismSearch(query);
        }
    } else if (path === 'home') {
        showStartPage();
    } else {
        alert('Unknown Prism protocol: ' + path);
    }
}

async function showStartPage() {
    // Hide all BrowserViews so start page is interactive
    try {
        await ipcRenderer.invoke('engine:hideAllViews');
    } catch (error) {
        console.error('Failed to hide views:', error);
    }
    
    startPage.classList.remove('hidden');
    addressBar.value = '';
}

async function updateNavButtons() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (tab && tab.url) {
        try {
            const canGoBack = await ipcRenderer.invoke('engine:canGoBack', tab.id);
            const canGoForward = await ipcRenderer.invoke('engine:canGoForward', tab.id);
            backBtn.disabled = !canGoBack;
            forwardBtn.disabled = !canGoForward;
        } catch (error) {
            backBtn.disabled = true;
            forwardBtn.disabled = true;
        }
    } else {
        backBtn.disabled = true;
        forwardBtn.disabled = true;
    }
}

function updateEngineBadge() {
    const tab = tabs.find(t => t.id === activeTabId);
    const engine = tab ? tab.engine : defaultEngine;
    
    const engineNames = {
        'prism': 'Prism',
        'chromium': 'Chromium',
        'firefox': 'Firefox',
        'tor': 'Tor'
    };
    
    engineBadge.textContent = engineNames[engine];
    engineBadge.className = 'engine-badge ' + engine;
    
    // Update selector to match active tab's engine
    if (engineSelector.value !== engine) {
        engineSelector.value = engine;
    }
}

// Handle engine events (title updates, loading state, etc.)
function handleEngineEvent(eventName, data) {
    console.log('Engine event:', eventName, data);
    
    const tab = tabs.find(t => t.id === data.tabId);
    if (!tab) return;

    switch (eventName) {
        case 'title-updated':
            tab.title = data.title || 'New Tab';
            renderTabs();
            break;
        case 'url-updated':
            tab.url = data.url;
            if (tab.id === activeTabId) {
                addressBar.value = data.url;
            }
            break;
        case 'loading-start':
            tab.isLoading = true;
            break;
        case 'loading-stop':
            tab.isLoading = false;
            updateNavButtons();
            break;
        case 'favicon-updated':
            // TODO: Handle favicon
            break;
    }
}

// Crypto Wallet Functions
async function createWallet() {
    const name = prompt('Wallet name:');
    if (!name) return;

    const password = prompt('Wallet password:');
    if (!password) return;

    try {
        const response = await fetch('http://localhost:8000/api/wallet/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, password })
        });

        const result = await response.json();
        if (result.success) {
            alert(`Wallet created!\n\n${result.wallet.address}`);
            loadWallets();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function importWallet() {
    const privateKey = prompt('Private key:');
    if (!privateKey) return;

    const name = prompt('Wallet name:');
    if (!name) return;

    const password = prompt('Wallet password:');
    if (!password) return;

    try {
        const response = await fetch('http://localhost:8000/api/wallet/import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ private_key: privateKey, name, password })
        });

        const result = await response.json();
        if (result.success) {
            alert(`Wallet imported!\n\n${result.wallet.address}`);
            loadWallets();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function loadWallets() {
    try {
        const response = await fetch('http://localhost:8000/api/wallet');
        const result = await response.json();
        
        if (result.success && result.wallets.length > 0) {
            displayWallets(result.wallets);
        }
    } catch (error) {
        console.error('Error loading wallets:', error);
    }
}

function displayWallets(wallets) {
    const walletList = document.getElementById('walletList');
    walletList.innerHTML = '';

    wallets.forEach(wallet => {
        const item = document.createElement('div');
        item.className = 'wallet-item';
        
        const balance = wallet.balance.ethereum || { native: 0 };
        
        item.innerHTML = `
            <div class="wallet-info">
                <h4>${wallet.name}</h4>
                <p>${wallet.address.substring(0, 10)}...${wallet.address.substring(wallet.address.length - 8)}</p>
            </div>
            <div class="wallet-balance">
                <div class="balance">${balance.native.toFixed(4)}</div>
                <div class="currency">ETH</div>
            </div>
        `;
        
        walletList.appendChild(item);
    });
}

// Extensions Page Functions
async function openExtensionsPage() {
    // Create a special extensions tab
    const tabId = `extensions-tab-${tabIdCounter++}`;
    const tab = {
        id: tabId,
        title: 'Extensions',
        url: 'prism://extensions',
        engine: 'prism',
        isLoading: false,
        isExtensionsTab: true
    };

    tabs.push(tab);
    
    // Update UI
    renderTabs();
    await switchToTab(tabId);
    
    // Show extensions page
    const extensionsPage = document.getElementById('extensionsPage');
    extensionsPage.style.display = 'flex';
    startPage.classList.add('hidden');
    aiPage.style.display = 'none';
    
    // Hide BrowserViews
    try {
        await ipcRenderer.invoke('engine:hideAllViews');
    } catch (error) {
        console.error('Failed to hide views:', error);
    }
    
    // Load featured extensions
    await loadFeaturedExtensions();
}

async function loadFeaturedExtensions() {
    try {
        const extensionsGrid = document.getElementById('extensionsContent');
        
        if (!extensionsGrid) {
            console.error('Extensions grid not found');
            return;
        }
        
        extensionsGrid.innerHTML = `<div class="loading-spinner">${Icons.loader} Loading featured extensions...</div>`;
        
        const featured = await ipcRenderer.invoke('extensions:featured');
        extensionsGrid.innerHTML = '';
        
        if (featured && featured.length > 0) {
            featured.forEach(ext => {
                const card = createExtensionCard(ext, false);
                extensionsGrid.appendChild(card);
            });
        } else {
            extensionsGrid.innerHTML = `<div class="no-results"><div style="font-size: 48px; margin-bottom: 16px; color: #007AFF;">${Icons.package}</div><div style="font-size: 18px; font-weight: 500;">No featured extensions available</div><div style="margin-top: 8px;">Check your internet connection</div></div>`;
        }
    } catch (error) {
        console.error('Failed to load featured extensions:', error);
        const extensionsGrid = document.getElementById('extensionsContent');
        if (extensionsGrid) {
            extensionsGrid.innerHTML = '<div class="no-results"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><div style="font-size: 18px; font-weight: 500; color: #ff3b30;">Failed to load extensions</div><div style="margin-top: 8px;">' + error.message + '</div></div>';
        }
    }
}

async function loadInstalledExtensions() {
    try {
        const extensionsGrid = document.getElementById('extensionsContent');
        
        if (!extensionsGrid) {
            console.error('Extensions grid not found');
            return;
        }
        
        extensionsGrid.innerHTML = `<div class="loading-spinner">${Icons.loader} Loading installed extensions...</div>`;
        
        const installed = await ipcRenderer.invoke('extensions:list');
        extensionsGrid.innerHTML = '';
        
        if (installed && installed.length > 0) {
            installed.forEach(ext => {
                const card = createExtensionCard(ext, true);
                extensionsGrid.appendChild(card);
            });
        } else {
            extensionsGrid.innerHTML = `<div class="no-results"><div style="font-size: 48px; margin-bottom: 16px; color: #007AFF;">${Icons.puzzle}</div><div style="font-size: 18px; font-weight: 500;">No extensions installed yet</div><div style="margin-top: 8px;">Browse featured extensions to get started</div></div>`;
        }
    } catch (error) {
        console.error('Failed to load installed extensions:', error);
        const extensionsGrid = document.getElementById('extensionsContent');
        if (extensionsGrid) {
            extensionsGrid.innerHTML = '<div class="no-results"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><div style="font-size: 18px; font-weight: 500; color: #ff3b30;">Failed to load extensions</div><div style="margin-top: 8px;">' + error.message + '</div></div>';
        }
    }
}

function createExtensionCard(ext, isInstalled = false) {
    const card = document.createElement('div');
    card.className = 'extension-card';
    
    // Extract icon URL - handle both Mozilla API format and installed format
    let iconUrl = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><text y="20" font-size="20">🧩</text></svg>';
    if (ext.icon && typeof ext.icon === 'string') {
        iconUrl = ext.icon;
    } else if (ext.icon && ext.icon['64']) {
        iconUrl = ext.icon['64'];
    } else if (ext.icons && ext.icons['64']) {
        iconUrl = ext.icons['64'];
    }
    
    // Extract name - handle both formats
    const name = ext.name && typeof ext.name === 'object' ? (ext.name['en-US'] || Object.values(ext.name)[0]) : (ext.name || 'Unknown Extension');
    
    // Extract description
    const description = ext.description && typeof ext.description === 'object' ? (ext.description['en-US'] || Object.values(ext.description)[0]) : (ext.description || 'No description available');
    
    // Extract stats
    const rating = ext.ratings?.average ? ext.ratings.average.toFixed(1) : (ext.rating || 'N/A');
    const users = ext.average_daily_users || ext.users || 0;
    
    // Get addon URL
    const addonUrl = ext.url || (ext.slug ? `https://addons.mozilla.org/firefox/addon/${ext.slug}/` : '');
    
    card.innerHTML = `
        <img class="extension-icon" src="${iconUrl}" alt="${name}" onerror="this.src='data:image/svg+xml,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 24 24&quot;><rect width=&quot;20&quot; height=&quot;20&quot; x=&quot;2&quot; y=&quot;2&quot; rx=&quot;2&quot; fill=&quot;%23007AFF&quot;/></svg>'">
        <div class="extension-info">
            <div class="extension-name">
                ${name}
                ${isInstalled ? '<span class="installed-badge">INSTALLED</span>' : ''}
            </div>
            <div class="extension-description">${description.length > 120 ? description.substring(0, 120) + '...' : description}</div>
            <div class="extension-stats">
                <span>${Icons.star} ${rating}</span>
                <span>${Icons.users} ${formatNumber(users)} users</span>
            </div>
        </div>
        <div class="extension-actions">
            ${isInstalled ? 
                `<button class="extension-btn extension-btn-uninstall" data-id="${ext.id}">Remove</button>` :
                `<button class="extension-btn extension-btn-install" data-url="${addonUrl}">${Icons.download} Install</button>`
            }
        </div>
    `;
    
    const button = card.querySelector('.extension-btn');
    button.addEventListener('click', async () => {
        button.disabled = true;
        button.textContent = isInstalled ? 'Removing...' : 'Installing...';
        
        try {
            if (isInstalled) {
                await handleUninstallExtension(ext.id);
            } else {
                await handleInstallExtension(addonUrl, name);
            }
        } finally {
            button.disabled = false;
        }
    });
    
    return card;
}

async function handleInstallExtension(url, name) {
    try {
        console.log('Installing extension:', name, url);
        const result = await ipcRenderer.invoke('extensions:installFromUrl', url);
        console.log('Extension installed:', result);
        
        // Show success notification
        showNotification(`${Icons.check} ${name} installed successfully!`, 'success');
        
        // Reload current tab view
        const activeTab = document.querySelector('.extensions-tab.active');
        if (activeTab?.dataset.tab === 'featured') {
            await loadFeaturedExtensions();
        } else if (activeTab?.dataset.tab === 'installed') {
            await loadInstalledExtensions();
        }
    } catch (error) {
        console.error('Failed to install extension:', error);
        showNotification(`${Icons.alertCircle} Failed to install ${name}: ${error.message}`, 'error');
    }
}

async function handleUninstallExtension(id) {
    try {
        console.log('Uninstalling extension:', id);
        await ipcRenderer.invoke('extensions:uninstall', id);
        console.log('Extension uninstalled');
        
        // Show success notification
        showNotification(`${Icons.check} Extension removed successfully!`, 'success');
        
        // Reload installed extensions view
        await loadInstalledExtensions();
    } catch (error) {
        console.error('Failed to uninstall extension:', error);
        showNotification(`${Icons.alertCircle} Failed to remove extension: ${error.message}`, 'error');
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'error' ? '#FF3B30' : type === 'success' ? '#34C759' : '#007AFF'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

// AI Assistant Tab Functions
async function openAITab(initialQuery = null) {
    // Create a special AI tab
    const tabId = `ai-tab-${tabIdCounter++}`;
    const tab = {
        id: tabId,
        title: 'AI Assistant',
        url: 'prism://ai',
        engine: 'prism',
        isLoading: false,
        isAITab: true
    };

    tabs.push(tab);
    currentAITabId = tabId;
    
    // Update UI
    renderTabs();
    await switchToTab(tabId);
    
    // Show AI page
    aiPage.style.display = 'flex';
    startPage.classList.add('hidden');
    
    // Clear previous messages
    aiTabMessages.innerHTML = '';
    aiWelcome.style.display = 'block';
    aiTabMessages.appendChild(aiWelcome);
    
    // If there's an initial query, send it
    if (initialQuery) {
        aiTabInput.value = initialQuery;
        await sendAIMessage(initialQuery);
        aiTabInput.value = '';
    }
}

async function sendAIMessage(query) {
    if (!query) return;
    
    // Remove welcome message if exists
    if (aiWelcome.parentElement) {
        aiWelcome.style.display = 'none';
    }
    
    // Add user message
    addAITabMessage('user', query);
    
    // Show loading
    const loadingMsg = addAITabMessage('assistant', '🤔 Thinking...');
    
    try {
        // Simulate AI response
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        // Remove loading
        loadingMsg.remove();
        
        // Generate response
        const response = generateSimulatedAIResponse(query);
        addAITabMessage('assistant', response);
        
    } catch (error) {
        console.error('AI error:', error);
        loadingMsg.remove();
        addAITabMessage('assistant', '❌ Sorry, I encountered an error. Please try again.');
    }
}

function addAITabMessage(role, content) {
    const messageEl = document.createElement('div');
    messageEl.className = `ai-message ${role}`;
    
    const bubbleEl = document.createElement('div');
    bubbleEl.className = 'ai-message-bubble';
    bubbleEl.textContent = content;
    
    messageEl.appendChild(bubbleEl);
    aiTabMessages.appendChild(messageEl);
    
    // Scroll to bottom
    aiTabMessages.scrollTop = aiTabMessages.scrollHeight;
    
    return messageEl;
}

function generateSimulatedAIResponse(query) {
    const lowerQuery = query.toLowerCase();
    
    if (lowerQuery.includes('quantum')) {
        return '🔬 Quantum computing uses quantum bits (qubits) that can exist in superposition, allowing them to process multiple states simultaneously. This enables exponentially faster computation for specific problems like cryptography, drug discovery, and optimization. Key principles include superposition, entanglement, and quantum interference.';
    } else if (lowerQuery.includes('blockchain')) {
        return '⛓️ Blockchain is a distributed ledger technology that records transactions across multiple computers. Each block contains data, a timestamp, and a cryptographic hash of the previous block, forming an immutable chain. This ensures transparency, security, and decentralization without requiring a central authority.';
    } else if (lowerQuery.includes('security') || lowerQuery.includes('privacy')) {
        return '🔒 Key web security tips:\n\n1. Use strong, unique passwords with a password manager\n2. Enable 2FA on all accounts\n3. Browse with HTTPS-only mode\n4. Use a VPN for public WiFi\n5. Clear cookies regularly\n6. Keep software updated\n7. Use privacy-focused browsers like Prism with Tor\n8. Avoid clicking suspicious links';
    } else if (lowerQuery.includes('prism') || lowerQuery.includes('haven')) {
        return '🌟 You\'re using Prism, part of the Haven family! We offer:\n\n• Multi-engine browsing (Chromium, Firefox, Tor, Prism)\n• HavenWallet for secure crypto management\n• HavenPay for safe online shopping\n• This AI assistant for instant help\n\nAll designed with privacy and security as top priorities!';
    } else {
        return `💡 That's an interesting question about "${query}"! While I'm currently a demo AI assistant, I can help with:\n\n• General knowledge questions\n• Web research assistance\n• Code explanations\n• Privacy and security tips\n\nIn the full version, I'll be powered by advanced AI models with real-time web access and deep integration with your browsing experience!`;
    }
}

// Global functions for wallet
window.createWallet = createWallet;
window.importWallet = importWallet;

// Start the app
init();

