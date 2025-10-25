/**
 * Renderer Process - Connects UI to Native Engines via IPC
 */

const { ipcRenderer } = require('electron');

// State
let tabs = [];
let activeTabId = null;
let tabIdCounter = 0;
let defaultEngine = 'firefox'; // Firefox is the default Prism engine
// Per-tab loading states
let tabLoadingStates = new Map(); // tabId -> { loading: boolean, progress: number }
// Sleeping tabs tracking
let sleepingTabs = new Set(); // tabId -> boolean
// Tab creation debouncing
let isCreatingTab = false;
// Initialization guard
let isInitialized = false;
// Start page showing guard
let isShowingStartPage = false;
// Tab switching debouncing
let isSwitchingTab = false;

// Removed complex performance monitoring - keeping it simple like real browsers

// Removed heavy site optimization - keeping it simple like real browsers

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
const engineIndicator = document.getElementById('engineIndicator');
const securityIndicator = document.getElementById('securityIndicator');
const loadingSpinner = document.getElementById('loadingSpinner');
const newTabBtn = document.getElementById('newTabBtn');

// History Elements
const historyModal = document.getElementById('historyModal');
const closeHistory = document.getElementById('closeHistory');
const historySearch = document.getElementById('historySearch');
const historyList = document.getElementById('historyList');
const historyCount = document.getElementById('historyCount');
const clearHistoryBtn = document.getElementById('clearHistoryBtn');

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

// No Internet Page Elements
const noInternetPage = document.getElementById('noInternetPage');
const retryButton = document.getElementById('retryButton');

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
    initializeToolbarIcons();
    
    // Wait for engines to be ready before creating first tab
    console.log('⏳ Waiting for engines to initialize...');
}

// Initialize toolbar button icons
function initializeToolbarIcons() {
    // Set icons for toolbar buttons
    const walletIcon = document.querySelector('#walletBtn .icon');
    const havenPayIcon = document.querySelector('#havenPayBtn .icon');
    const aiIcon = document.querySelector('#aiBtn .icon');
    const historyIcon = document.querySelector('#historyBtn .icon');
    const extensionsIcon = document.querySelector('#extensionsBtn .icon');
    const settingsIcon = document.querySelector('#settingsBtn .icon');
    const securityIcon = document.querySelector('#securityIndicator .icon');
    
    if (walletIcon) walletIcon.innerHTML = Icons.wallet;
    if (havenPayIcon) havenPayIcon.innerHTML = Icons.creditCard;
    if (aiIcon) aiIcon.innerHTML = Icons.bot;
    if (historyIcon) historyIcon.innerHTML = Icons.history;
    if (extensionsIcon) extensionsIcon.innerHTML = Icons.puzzle;
    if (settingsIcon) settingsIcon.innerHTML = Icons.settings;
    if (securityIcon) securityIcon.innerHTML = Icons.lock;
}

// Listen for dependency check results
ipcRenderer.on('dependencies-checked', (event, result) => {
    console.log('📋 Dependencies checked:', result);
    showDependencyStatus(result);
});

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

    // New tab button
    if (newTabBtn) {
        newTabBtn.addEventListener('click', () => {
            createNewTab();
        });
    }
    
    // No internet retry button
    if (retryButton) {
        retryButton.addEventListener('click', () => {
            hideNoInternetPage();
            // Try to reload the current tab
            const activeTab = tabs.find(t => t.id === activeTabId);
            if (activeTab && activeTab.url) {
                navigateTo(activeTab.url);
            }
        });
    }

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
            
            // Special tabs (AI, Extensions) can't switch engines, but start page can
            if (tab.isAITab || tab.isExtensionsTab) {
                console.log('Cannot switch engine for AI/Extension tabs');
                engineSelector.value = tab.engine;
                return;
            }
            
            try {
                // Switch engine for this tab
                const result = await ipcRenderer.invoke('engine:switchTabEngine', tab.id, newEngine);
                
                if (result.success) {
                    // Update tab engine
                    tab.engine = newEngine;
                    
                    // Update UI
                    updateEngineBadge();
                    renderTabs();
                    
                    // Show notification for Tor
                    if (newEngine === 'tor') {
                        console.log('🔒 Switched to Tor - Full amnesia mode: No history, no cache, no tracking');
                    }
                    
                    console.log(`✅ Tab "${tab.title}" switched to: ${newEngine}`);
                } else {
                    throw new Error(result.message || 'Switch failed');
                }
            } catch (error) {
                console.error(`❌ Failed to switch to ${newEngine}:`, error);
                alert(`Failed to switch to ${newEngine}: ${error.message}\n\nPlease try:\n1. Refreshing the page\n2. Creating a new tab\n3. Restarting the browser`);
                // Revert selector
                engineSelector.value = tab.engine;
            }
        }
    });

    // History
    const historyBtn = document.getElementById('historyBtn');
    if (historyBtn) {
        historyBtn.addEventListener('click', async () => {
            await openHistory();
        });
    }
    
    // Extensions
    extensionsBtn.addEventListener('click', async () => {
        // Open extensions page as a special tab
        await openExtensionsPage();
    });
    
    // Settings (placeholder for now)
    const settingsBtn = document.getElementById('settingsBtn');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', () => {
            alert('Settings page coming soon! This is where you can:\n\n• Manage user accounts\n• Configure default engine\n• Manage saved passwords\n• Clear browsing data\n• Customize appearance\n• And more...');
        });
    }

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
        // Don't hide all views - just show modal on top
        walletModal.classList.add('show');
    });

    closeWallet.addEventListener('click', async () => {
        walletModal.classList.remove('show');
        // No need to show active view - it's already there
    });

    walletModal.addEventListener('click', async (e) => {
        if (e.target === walletModal) {
            walletModal.classList.remove('show');
            // No need to show active view - it's already there
        }
    });

    // HavenPay modal
    havenPayBtn.addEventListener('click', async () => {
        // Don't hide all views - just show modal on top
        havenPayModal.classList.add('show');
    });

    closeHavenPay.addEventListener('click', async () => {
        havenPayModal.classList.remove('show');
        // No need to show active view - it's already there
    });

    havenPayModal.addEventListener('click', async (e) => {
        if (e.target === havenPayModal) {
            havenPayModal.classList.remove('show');
            // No need to show active view - it's already there
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
    try {
        const quickLinks = document.querySelectorAll('.quick-link');
        console.log('🔗 Setting up quick links:', quickLinks.length);
        
        quickLinks.forEach((item, index) => {
            // Check if already has event listener to prevent duplicates
            if (item.dataset.listenerAdded === 'true') {
                console.log(`Quick link ${index} already has listener, skipping`);
                return;
            }
            
            // Mark as having listener
            item.dataset.listenerAdded = 'true';
            
            item.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const url = item.getAttribute('data-url');
                console.log('🔗 Quick link clicked:', url, 'Current tabs:', tabs.length, 'Active:', activeTabId);
                
                if (url) {
                    try {
                        // Ensure we have a valid active tab
                        const activeTab = tabs.find(t => t.id === activeTabId);
                        
                        if (!activeTab || activeTab.isAITab || activeTab.isExtensionsTab || activeTab.url) {
                            // Create a new tab if no valid tab exists or current tab is special or already has content
                            console.log('🆕 Creating new tab for quick link');
                            await createNewTab();
                            // Wait a bit for tab to be fully created
                            await new Promise(resolve => setTimeout(resolve, 50));
                        }
                        
                        // Navigate to the URL
                        console.log('🌐 Navigating to:', url);
                        await navigateTo(url);
                    } catch (error) {
                        console.error('❌ Quick link navigation error:', error);
                        alert('Failed to open link: ' + error.message);
                    }
                }
            });
        });
        
        console.log('✅ Quick links setup completed');
    } catch (error) {
        console.error('❌ Failed to setup quick links:', error);
    }
}

    // Haven Service Cards - Removed from home page but keep modal functionality

    // Listen for engine events
    ipcRenderer.on('engine-event', (event, { event: eventName, data }) => {
        handleEngineEvent(eventName, data);
    });

    // Listen for network errors
    ipcRenderer.on('network-error', (event, { tabId, error, errorCode }) => {
        console.log('Network error:', error, 'for tab:', tabId);
        showNoInternetPage();
    });

    // Menu shortcuts - remove existing listeners first to prevent duplicates
    ipcRenderer.removeAllListeners('new-tab');
    ipcRenderer.removeAllListeners('close-tab');
    ipcRenderer.removeAllListeners('navigate-back');
    ipcRenderer.removeAllListeners('navigate-forward');
    ipcRenderer.removeAllListeners('navigate-refresh');
    ipcRenderer.removeAllListeners('navigate-home');
    
    // Register new listeners
    ipcRenderer.on('new-tab', async () => await createNewTabDebounced());
    ipcRenderer.on('close-tab', async () => await closeTab(activeTabId));
    ipcRenderer.on('navigate-back', () => backBtn.click());
    ipcRenderer.on('navigate-forward', () => forwardBtn.click());
    ipcRenderer.on('navigate-refresh', () => refreshBtn.click());
    ipcRenderer.on('navigate-home', () => showStartPage());
    
    // History Modal
    if (closeHistory) {
        closeHistory.addEventListener('click', () => {
            historyModal.classList.remove('active');
            ipcRenderer.invoke('engine:showActiveView').catch(err => console.error(err));
        });
    }
    
    if (historySearch) {
        let searchTimeout;
        historySearch.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadHistory(100, historySearch.value);
            }, 300);
        });
    }
    
    if (clearHistoryBtn) {
        clearHistoryBtn.addEventListener('click', async () => {
            if (confirm('Are you sure you want to clear all browsing history? This cannot be undone.')) {
                const result = await ipcRenderer.invoke('data:clearHistory');
                if (result.success) {
                    await loadHistory();
                    alert('History cleared successfully');
                } else {
                    alert('Failed to clear history: ' + result.error);
                }
            }
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', async (e) => {
        // Cmd+Y (or Ctrl+Y on Windows/Linux) to open history
        if ((e.metaKey || e.ctrlKey) && e.key === 'y') {
            e.preventDefault();
            await openHistory();
        }
    });
    
    // Dependency Modal
    const closeDependencies = document.getElementById('closeDependencies');
    if (closeDependencies) {
        closeDependencies.addEventListener('click', () => {
            const modal = document.getElementById('dependencyModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
            }
        });
    }
    
    const recheckDependencies = document.getElementById('recheckDependencies');
    if (recheckDependencies) {
        recheckDependencies.addEventListener('click', async () => {
            const statusEl = document.getElementById('dependencyStatus');
            if (statusEl) {
                statusEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #86868b;"><div style="font-size: 32px; margin-bottom: 16px;">⏳</div><div>Rechecking dependencies...</div></div>';
            }
            const result = await ipcRenderer.invoke('dependencies:check');
            showDependencyStatus(result);
        });
    }

// Tab Management
// Tab Management - SIMPLE AND WORKING
function createNewTab() {
    const tabId = `tab-${tabIdCounter++}`;
    const tab = {
        id: tabId,
        title: 'New Tab',
        url: '',
        engine: defaultEngine,
        isLoading: false,
        visible: false,
        view: null
    };

    tabs.push(tab);
    activeTabId = tabId;
    
    // Update UI immediately
    renderTabs();
    updateEngineBadge();
    
    // CRITICAL: Hide ALL other tabs immediately to prevent content bleeding
    console.log('🔒 Creating new tab - hiding all other tabs');
    tabs.forEach(otherTab => {
        if (otherTab.id !== tabId && otherTab.visible && otherTab.view) {
            console.log('Hiding tab:', otherTab.id);
            ipcRenderer.invoke('engine:hideTab', otherTab.id).catch(err => {
                console.error('Failed to hide tab:', err);
            });
            otherTab.visible = false;
        }
    });
    
    // CRITICAL: Hide all BrowserViews to ensure clean state
    ipcRenderer.invoke('engine:hideAllViews').catch(err => {
        console.error('Failed to hide all views:', err);
    });
    
    // Show start page
    startPage.classList.remove('hidden');
    addressBar.value = '';
    
    // Create engine tab in background
    ipcRenderer.invoke('engine:createTab', tabId, defaultEngine, {})
        .then(result => {
            tab.view = result.view;
            tab.visible = true;
            console.log('✅ Tab created successfully');
        })
        .catch(error => {
            console.error('Failed to create tab:', error);
            // Remove failed tab
            const index = tabs.findIndex(t => t.id === tabId);
            if (index > -1) {
                tabs.splice(index, 1);
                if (tabs.length > 0) {
                    switchToTab(tabs[tabs.length - 1].id);
                } else {
                    activeTabId = null;
                }
                renderTabs();
            }
        });
}

function switchToTab(tabId) {
    const oldActiveTabId = activeTabId;
    activeTabId = tabId;
    const tab = tabs.find(t => t.id === tabId);
    if (!tab) return;
    
    // Update active tab styling
    const allTabs = tabBar.querySelectorAll('.tab');
    allTabs.forEach(tabEl => {
        if (tabEl.dataset.tabId === tabId) {
            tabEl.classList.add('active');
        } else {
            tabEl.classList.remove('active');
        }
    });
    
    updateEngineBadge();
    
    // Handle special tabs
    if (tab.isAITab) {
        aiPage.style.display = 'flex';
        startPage.classList.add('hidden');
        addressBar.value = 'prism://ai';
        return;
    }
    
    if (tab.isExtensionsTab) {
        const extensionsPage = document.getElementById('extensionsPage');
        if (extensionsPage) extensionsPage.style.display = 'flex';
        startPage.classList.add('hidden');
        addressBar.value = 'prism://extensions';
        return;
    }
    
    // Hide special pages
    aiPage.style.display = 'none';
    const extensionsPage = document.getElementById('extensionsPage');
    if (extensionsPage) extensionsPage.style.display = 'none';
    
    // Update address bar
    if (tab.url) {
        addressBar.value = tab.url;
        updateSecurityIndicator(tab.url);
        startPage.classList.add('hidden');
    } else {
        addressBar.value = '';
        updateSecurityIndicator('');
        startPage.classList.remove('hidden');
    }
    
    // Hide other tabs and show current tab
    if (tab.view) {
        // CRITICAL: Hide ALL other tabs first to prevent content bleeding
        console.log('🔒 Switching to tab - hiding all other tabs');
        tabs.forEach(t => {
            if (t.id !== tabId && t.visible && t.view) {
                console.log('Hiding tab:', t.id);
                ipcRenderer.invoke('engine:hideTab', t.id).catch(err => {
                    console.error('Failed to hide tab:', err);
                });
                t.visible = false;
            }
        });
        
        // CRITICAL: Use hideOtherViews to ensure clean state
        ipcRenderer.invoke('engine:hideOtherViews', tabId)
            .then(() => {
                // Show current tab
                return ipcRenderer.invoke('engine:showTab', tabId);
            })
            .then(() => {
                tab.visible = true;
                updateNavButtons();
                console.log('✅ Tab switched successfully');
            })
            .catch(error => {
                console.error('Failed to switch tab:', error);
            });
    } else {
        updateNavButtons();
    }
}

async function navigateTo(input) {
    if (!input) return;

    let url = input.trim();
    
    // Get current active tab
    let tab = tabs.find(t => t.id === activeTabId);
    
    // Create new tab if none exists
    if (!tab) {
        await createNewTab();
        tab = tabs.find(t => t.id === activeTabId);
        if (!tab) return;
    }
    
    // Don't navigate in special tabs
    if (tab.isAITab || tab.isExtensionsTab) return;

    // Handle prism:// protocol
    if (url.startsWith('prism://')) {
        handlePrismProtocol(url);
        return;
    }

    // Use the tab's engine
    const tabEngine = tab.engine || defaultEngine;
    
    // Check if it's a URL or search query
    const isSearch = !url.includes('.') || url.includes(' ');
    
    if (tabEngine === 'prism' && isSearch) {
        performPrismSearch(url);
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

    // Update tab state immediately
    tab.url = url;
    tab.isLoading = true;
    
    // Update UI immediately
    addressBar.value = url;
    updateSecurityIndicator(url);
    startPage.classList.add('hidden');
    renderTabs();

    // Navigate and ensure BrowserView is visible
    ipcRenderer.invoke('engine:navigate', tab.id, url)
        .then(() => {
            // Ensure the tab is visible after navigation
            return ipcRenderer.invoke('engine:showTab', tab.id);
        })
        .then(() => {
            // Don't set isLoading = false here - let the engine events handle it
            hideNoInternetPage(); // Hide no internet page on successful navigation
        })
        .catch(error => {
            console.error('Navigation failed:', error);
            tab.isLoading = false;
            
            // Check if it's a network error
            if (error.message && (
                error.message.includes('net::') ||
                error.message.includes('ERR_INTERNET_DISCONNECTED') ||
                error.message.includes('ERR_NETWORK_CHANGED')
            )) {
                showNoInternetPage();
            }
        });
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

    // Clean up loading state
    tabLoadingStates.delete(tabId);
    sleepingTabs.delete(tabId);
    
    // Remove from tabs array
    tabs.splice(index, 1);

    if (tabs.length === 0) {
        // Show start page instead of creating a new tab
        showStartPage();
        activeTabId = null;
        renderTabs();
    } else if (activeTabId === tabId) {
        // Calculate new active index AFTER splicing
        const newActiveIndex = Math.min(index, tabs.length - 1);
        const newActiveTab = tabs[newActiveIndex];
        if (newActiveTab) {
            await switchToTab(newActiveTab.id);
        }
        renderTabs();
    } else {
        renderTabs();
    }
}

// Removed old broken switchToTab function - replaced with master handler

// Removed tab element caching - keeping it simple like real browsers

// PROPER BROWSER-LIKE TAB RENDERING - Keep everything in memory
function renderTabs() {
    if (!tabBar) return;
    
    // Get existing tab elements
    const existingTabs = Array.from(tabBar.querySelectorAll('.tab'));
    const existingTabIds = new Set(existingTabs.map(el => el.dataset.tabId));
    const currentTabIds = new Set(tabs.map(tab => tab.id));
    
    // Remove tabs that no longer exist
    existingTabs.forEach(tabEl => {
        if (!currentTabIds.has(tabEl.dataset.tabId)) {
            tabEl.remove();
        }
    });
    
    // Update or create tabs
    tabs.forEach((tab, index) => {
        let tabElement = tabBar.querySelector(`[data-tab-id="${tab.id}"]`);
        
        if (!tabElement) {
            // Create new tab element
            tabElement = document.createElement('div');
            tabElement.className = 'tab';
            tabElement.dataset.tabId = tab.id;
            
            tabElement.innerHTML = `
                <div class="tab-favicon">
                    <div class="favicon-placeholder"></div>
                    <div class="tab-loading"></div>
                </div>
                <div class="tab-engine-indicator"></div>
                <div class="tab-title"></div>
                <div class="tab-close" data-tab-id="${tab.id}">×</div>
            `;
            
            // Add event listeners only once
            tabElement.addEventListener('click', (e) => {
                if (!e.target.classList.contains('tab-close')) {
                    switchToTabDebounced(tab.id);
                }
            });
            
            const closeButton = tabElement.querySelector('.tab-close');
            closeButton.addEventListener('click', (e) => {
                e.stopPropagation();
                closeTab(tab.id);
            });
            
            // Mark as having listeners to prevent duplicates
            tabElement.dataset.listenersAdded = 'true';
            
            // Insert before the new tab button
            tabBar.insertBefore(tabElement, newTabBtn);
        }
        
        // Update tab content
        const titleEl = tabElement.querySelector('.tab-title');
        const loadingEl = tabElement.querySelector('.tab-loading');
        const indicatorEl = tabElement.querySelector('.tab-engine-indicator');
        
        if (titleEl) titleEl.textContent = tab.title || 'New Tab';
        if (loadingEl) {
            loadingEl.className = `tab-loading ${tab.isLoading ? 'loading' : ''}`;
        }
        if (indicatorEl) {
            indicatorEl.className = `tab-engine-indicator ${tab.engine || 'firefox'}`;
        }
        
        // Update active state
        if (tab.id === activeTabId) {
            tabElement.classList.add('active');
        } else {
            tabElement.classList.remove('active');
        }
    });
}

// Smooth scrolling optimization for AI messages
function smoothScrollToBottom(element) {
    if (!element) return;

    // Use requestAnimationFrame for smooth scrolling
    requestAnimationFrame(() => {
        element.scrollTo({
            top: element.scrollHeight,
            behavior: 'smooth'
        });
    });
}

// Per-tab loading state management
function setTabLoading(tabId, loading, progress = 0) {
    if (loading) {
        tabLoadingStates.set(tabId, { loading: true, progress });
    } else {
        tabLoadingStates.set(tabId, { loading: false, progress: 0 });
    }
    
    // Update UI if this is the active tab
    if (tabId === activeTabId) {
        updateLoadingSpinner(loading);
    }
}

function updateLoadingSpinner(loading) {
    // Disabled address bar loading spinner to prevent white screen issues
    // Loading state is now only shown in tab bar indicators
    // if (loading) {
    //     loadingSpinner.style.display = 'block';
    // } else {
    //     loadingSpinner.style.display = 'none';
    // }
}

function getTabLoadingState(tabId) {
    return tabLoadingStates.get(tabId) || { loading: false, progress: 0 };
}

// Sleeping tab management
async function checkTabSleeping(tabId) {
    try {
        const isSleeping = await ipcRenderer.invoke('engine:isTabSleeping', tabId);
        if (isSleeping) {
            sleepingTabs.add(tabId);
            return true;
        } else {
            sleepingTabs.delete(tabId);
            return false;
        }
    } catch (error) {
        console.error('Failed to check tab sleeping state:', error);
        return false;
    }
}

async function wakeSleepingTab(tabId) {
    try {
        const result = await ipcRenderer.invoke('engine:wakeTabFromSleep', tabId);
        if (result.success) {
            sleepingTabs.delete(tabId);
            // Update tab info with restored data
            const tab = tabs.find(t => t.id === tabId);
            if (tab && result.url) {
                tab.url = result.url;
                tab.title = result.title || 'Restored Tab';
            }
            return true;
        }
        return false;
    } catch (error) {
        console.error('Failed to wake sleeping tab:', error);
        return false;
    }
}

function isTabSleeping(tabId) {
    return sleepingTabs.has(tabId);
}

// Debounced tab creation to prevent multiple tabs from Cmd+T
async function createNewTabDebounced() {
    if (isCreatingTab) {
        console.log('Tab creation already in progress, ignoring...');
        return;
    }
    
    isCreatingTab = true;
    try {
        await createNewTab();
    } catch (error) {
        console.error('Failed to create tab in debounced function:', error);
    } finally {
        // Reset flag immediately after creation completes
        isCreatingTab = false;
    }
}

// Debounced tab switching to prevent race conditions
async function switchToTabDebounced(tabId) {
    if (isSwitchingTab) {
        console.log('Tab switching already in progress, skipping...');
        return;
    }
    
    isSwitchingTab = true;
    
    // Add timeout to prevent getting stuck
    const timeout = setTimeout(() => {
        console.warn('Tab switching timeout, resetting flag');
        isSwitchingTab = false;
        // Force update UI in case it got stuck
        updateNavButtons();
    }, 2000);
    
    try {
        await switchToTab(tabId);
    } catch (error) {
        console.error('Failed to switch tab in debounced function:', error);
        // Force recovery
        const tab = tabs.find(t => t.id === tabId);
        if (tab) {
            tab.visible = true;
        }
        updateNavButtons();
    } finally {
        clearTimeout(timeout);
        isSwitchingTab = false;
    }
}

// Immediate render without debouncing (for initial load)
function renderTabsImmediate() {
    // Performance: Only update changed tabs instead of rebuilding everything
    const currentTabIds = new Set(tabs.map(t => t.id));
    
    // Remove deleted tabs from cache
    for (const [tabId, element] of tabElementCache.entries()) {
        if (!currentTabIds.has(tabId)) {
            element.remove();
            tabElementCache.delete(tabId);
        }
    }
    
    // Update or create tabs
    tabs.forEach((tab, index) => {
        let tabEl = tabElementCache.get(tab.id);
        const isActive = tab.id === activeTabId;
        
        if (!tabEl) {
            // Create new tab element
            tabEl = document.createElement('div');
            tabEl.setAttribute('draggable', 'true');
            tabEl.dataset.tabId = tab.id;
            tabElementCache.set(tab.id, tabEl);
        }
        
        // Update tab state (minimal DOM manipulation)
        tabEl.className = `tab ${isActive ? 'active' : ''}`;
        tabEl.dataset.tabIndex = index;
        
        // Performance: Only rebuild tab content if it doesn't exist
        if (!tabEl.hasChildNodes()) {
            // Color-coded by engine
            const engineColors = {
                'tor': '#7C3AED',
                'prism': '#34C759',
                'chromium': '#007AFF',
                'firefox': '#FF9500'
            };
            
            const faviconEl = document.createElement('div');
            faviconEl.className = 'tab-favicon';
            faviconEl.textContent = tab.url ? '●' : '○';
            faviconEl.style.color = engineColors[tab.engine] || '#86868b';
            
            const titleEl = document.createElement('div');
            titleEl.className = 'tab-title';
            
            // Check if tab is sleeping and add indicator
            const isSleeping = sleepingTabs.has(tab.id);
            if (isSleeping) {
                titleEl.innerHTML = `😴 ${tab.title || 'Sleeping Tab'}`;
                titleEl.title = `${tab.title || 'Sleeping Tab'} (Sleeping - Click to wake)`;
            } else {
                titleEl.textContent = tab.title || 'New Tab';
                titleEl.title = tab.title || 'New Tab';
            }
            
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
        } else {
            // Just update title and favicon if already built
            const titleEl = tabEl.querySelector('.tab-title');
            const faviconEl = tabEl.querySelector('.tab-favicon');
            
            // Check if tab is sleeping and update accordingly
            const isSleeping = sleepingTabs.has(tab.id);
            if (titleEl) {
                if (isSleeping) {
                    titleEl.innerHTML = `😴 ${tab.title || 'Sleeping Tab'}`;
                    titleEl.title = `${tab.title || 'Sleeping Tab'} (Sleeping - Click to wake)`;
                } else {
                    titleEl.textContent = tab.title || 'New Tab';
                    titleEl.title = tab.title || 'New Tab';
                }
            }
            
            if (faviconEl) {
                faviconEl.textContent = tab.url ? '●' : '○';
                const engineColors = {
                    'tor': '#7C3AED',
                    'prism': '#34C759',
                    'chromium': '#007AFF',
                    'firefox': '#FF9500'
                };
                faviconEl.style.color = engineColors[tab.engine] || '#86868b';
            }
        }

        // Ensure correct position in DOM
        const currentIndex = Array.from(tabBar.children).indexOf(tabEl);
        if (currentIndex !== index) {
            if (index >= tabBar.children.length - 1) {
                tabBar.insertBefore(tabEl, tabBar.lastChild); // Before new tab button
            } else {
                tabBar.insertBefore(tabEl, tabBar.children[index]);
            }
        }
    });

    // Add new tab button if it doesn't exist
    let newTabBtn = tabBar.querySelector('.tab-new');
    if (!newTabBtn) {
        newTabBtn = document.createElement('button');
        newTabBtn.className = 'tab-new';
        newTabBtn.textContent = '+';
        newTabBtn.addEventListener('click', createNewTabDebounced);
        tabBar.appendChild(newTabBtn);
    }
}

// Tab drag and drop handlers
let draggedTab = null;
let draggedTabIndex = null;

function handleTabDragStart(e) {
    draggedTab = e.currentTarget;
    draggedTabIndex = parseInt(draggedTab.dataset.tabIndex);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', draggedTab.innerHTML);
    
    // Add dragging class for cursor
    draggedTab.classList.add('dragging');
    
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
    // Reset opacity and remove dragging class
    if (draggedTab) {
        draggedTab.style.opacity = '1';
        draggedTab.classList.remove('dragging');
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
    
    // Get current active tab
    let tab = tabs.find(t => t.id === activeTabId);
    
    // Create new tab if none exists
    if (!tab) {
        await createNewTab();
        tab = tabs.find(t => t.id === activeTabId);
        if (!tab) return;
    }
    
    // Don't navigate in special tabs
    if (tab.isAITab || tab.isExtensionsTab) return;

    // Handle prism:// protocol
    if (url.startsWith('prism://')) {
        handlePrismProtocol(url);
        return;
    }

    // Use the tab's engine
    const tabEngine = tab.engine || defaultEngine;
    
    // Check if it's a URL or search query
    const isSearch = !url.includes('.') || url.includes(' ');
    
    if (tabEngine === 'prism' && isSearch) {
        performPrismSearch(url);
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

    // Update tab state immediately
    tab.url = url;
    tab.isLoading = true;
    
    // Update UI immediately
    addressBar.value = url;
    updateSecurityIndicator(url);
    startPage.classList.add('hidden');
    renderTabs();

    // Navigate and ensure BrowserView is visible
    ipcRenderer.invoke('engine:navigate', tab.id, url)
        .then(() => {
            // Ensure the tab is visible after navigation
            return ipcRenderer.invoke('engine:showTab', tab.id);
        })
        .then(() => {
            // Don't set isLoading = false here - let the engine events handle it
            // This prevents premature loading state clearing that causes white screens
            hideNoInternetPage(); // Hide no internet page on successful navigation
        })
        .catch(error => {
            console.error('Navigation failed:', error);
            tab.isLoading = false;
            
            // Check if it's a network error
            if (error.message && (
                error.message.includes('net::') ||
                error.message.includes('ERR_INTERNET_DISCONNECTED') ||
                error.message.includes('ERR_NETWORK_CHANGED') ||
                error.message.includes('ERR_CONNECTION_REFUSED') ||
                error.message.includes('ERR_NAME_NOT_RESOLVED')
            )) {
                showNoInternetPage();
            }
            
            renderTabs();
        });
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

// Hide all views to prevent content bleeding
async function hideAllViews() {
    try {
        await ipcRenderer.invoke('engine:hideAllViews');
    } catch (error) {
        console.error('Failed to hide all views:', error);
    }
}

// Hide all OTHER views except the specified tab to prevent content bleeding
async function hideOtherViews(currentTabId) {
    try {
        await ipcRenderer.invoke('engine:hideOtherViews', currentTabId);
    } catch (error) {
        console.error('Failed to hide other views:', error);
    }
}

async function showStartPage() {
    // Prevent multiple simultaneous calls
    if (isShowingStartPage) {
        console.log('🏠 Start page already being shown, skipping...');
        return;
    }
    
    isShowingStartPage = true;
    
    try {
        console.log('🏠 Showing start page...');
        
        // CRITICAL: Hide all views to prevent content bleeding
        hideAllViews().catch(err => console.error('Failed to hide views:', err));
        
        // Show start page IMMEDIATELY
        startPage.classList.remove('hidden');
        addressBar.value = '';
        
        // Re-setup quick links to ensure they work (NON-BLOCKING)
        setupQuickLinks();
        
        console.log('✅ Start page shown successfully');
    } catch (error) {
        console.error('❌ Failed to show start page:', error);
        // Fallback: just show the page even if other operations fail
        startPage.classList.remove('hidden');
        addressBar.value = '';
    } finally {
        isShowingStartPage = false;
    }
}

// No internet page management
function showNoInternetPage() {
    if (noInternetPage) {
        noInternetPage.classList.remove('hidden');
        startPage.classList.add('hidden');
        aiPage.style.display = 'none';
        const extensionsPage = document.getElementById('extensionsPage');
        if (extensionsPage) extensionsPage.style.display = 'none';
    }
}

function hideNoInternetPage() {
    if (noInternetPage) {
        noInternetPage.classList.add('hidden');
    }
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
    
    // Update engine indicator
    updateEngineIndicator(engine);
}

function updateEngineIndicator(engine) {
    if (!engineIndicator) return;
    
    // Remove all engine classes
    engineIndicator.className = 'engine-indicator';
    
    // Add the appropriate engine class
    if (engine === 'prism' || engine === 'chromium' || engine === 'firefox' || engine === 'tor') {
        engineIndicator.classList.add(engine);
    } else {
        // Default to firefox if unknown engine
        engineIndicator.classList.add('firefox');
    }
}


// Loading Bar Functions
// Loading bar removed - was causing issues

// Security Indicator Functions
function updateSecurityIndicator(url) {
    if (!securityIndicator) return;
    
    const iconElement = securityIndicator.querySelector('.icon');
    if (!iconElement) return;
    
    // Reset classes
    securityIndicator.classList.remove('secure', 'insecure');
    
    if (!url || url === '' || url.startsWith('prism://')) {
        // Internal pages or no URL
        iconElement.innerHTML = Icons.lock;
        securityIndicator.title = 'Internal page';
        securityIndicator.style.display = 'none';
    } else if (url.startsWith('https://')) {
        // Secure HTTPS connection
        iconElement.innerHTML = Icons.lock;
        securityIndicator.classList.add('secure');
        securityIndicator.title = 'Secure connection (HTTPS)';
        securityIndicator.style.display = 'flex';
    } else if (url.startsWith('http://')) {
        // Insecure HTTP connection
        iconElement.innerHTML = Icons.shieldAlert;
        securityIndicator.classList.add('insecure');
        securityIndicator.title = 'Not secure (HTTP)';
        securityIndicator.style.display = 'flex';
    } else {
        // Other protocols (file://, etc.)
        iconElement.innerHTML = Icons.lock;
        securityIndicator.title = 'Local resource';
        securityIndicator.style.display = 'none';
    }
}

// Handle engine events (title updates, loading state, etc.)
function handleEngineEvent(eventName, data) {
    // Removed console.log for performance - causes jitter
    
    const tab = tabs.find(t => t.id === data.tabId);
    if (!tab) return;

    switch (eventName) {
        case 'title-updated':
            tab.title = data.title || 'New Tab';
            renderTabs();
            break;
        case 'navigation':
        case 'url-updated':
            // Update tab URL
            tab.url = data.url;
            // Update address bar and security indicator if this is the active tab
            if (tab.id === activeTabId) {
                addressBar.value = data.url;
                updateSecurityIndicator(data.url);
            }
            break;
        case 'loading-start':
            tab.isLoading = true;
            // Update loading spinner if this is the active tab
            if (tab.id === activeTabId) {
                updateLoadingSpinner(true);
            }
            // Only update tab bar, don't call full renderTabs() to prevent interference
            // renderTabs();
            break;
        case 'loading-stop':
            tab.isLoading = false;
            // Update loading spinner if this is the active tab
            if (tab.id === activeTabId) {
                updateLoadingSpinner(false);
            }
            // Add to browsing history when page finishes loading
            // NEVER track Tor browsing - complete amnesia mode
            if (data.url && data.url !== 'about:blank' && !data.url.startsWith('prism://') && tab.engine !== 'tor') {
                ipcRenderer.invoke('data:addToHistory', data.url, data.title || tab.title, tab.engine).catch(err => {
                    console.error('Failed to add to history:', err);
                });
            }
            updateNavButtons();
            // Don't call renderTabs() here to prevent interference with web content
            // renderTabs();
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
    
    // Don't hide BrowserViews - let background tabs continue loading
    // try {
    //     await ipcRenderer.invoke('engine:hideAllViews');
    // } catch (error) {
    //     console.error('Failed to hide views:', error);
    // }
    
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
    // Check if AI tab already exists
    const existingAITab = tabs.find(t => t.isAITab);
    if (existingAITab) {
        await switchToTab(existingAITab.id);
        return;
    }
    
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
    
    // Initialize loading state
    setTabLoading(tabId, false);
    
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
    
    // Scroll to bottom with smooth animation
    smoothScrollToBottom(aiTabMessages);
    
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
        return '🌟 You\'re using Prism, Built by Hamish Leahy - Privacy First Browsing! We offer:\n\n• Multi-engine browsing (Chromium, Firefox, Tor, Prism)\n• HavenWallet for secure crypto management\n• HavenPay for safe online shopping\n• This AI assistant for instant help\n\nAll designed with privacy and security as top priorities!';
    } else {
        return `💡 That's an interesting question about "${query}"! While I'm currently a demo AI assistant, I can help with:\n\n• General knowledge questions\n• Web research assistance\n• Code explanations\n• Privacy and security tips\n\nIn the full version, I'll be powered by advanced AI models with real-time web access and deep integration with your browsing experience!`;
    }
}

// Global functions for wallet
window.createWallet = createWallet;
window.importWallet = importWallet;

// ===== HISTORY FUNCTIONS =====

async function openHistory() {
    console.log('Opening history...');
    
    // Don't hide BrowserViews - let background tabs continue loading
    // try {
    //     await ipcRenderer.invoke('engine:hideAllViews');
    // } catch (error) {
    //     console.error('Failed to hide views:', error);
    // }
    
    // Load and show history
    await loadHistory();
    historyModal.classList.add('active');
}

async function loadHistory(limit = 100, searchQuery = '') {
    try {
        const history = await ipcRenderer.invoke('data:getHistory', limit, searchQuery);
        
        // Update count
        historyCount.textContent = `${history.length} item${history.length !== 1 ? 's' : ''}`;
        
        // Clear list
        historyList.innerHTML = '';
        
        if (history.length === 0) {
            historyList.innerHTML = '<div style="text-align: center; padding: 40px; color: #86868b;">No history found</div>';
            return;
        }
        
        // Group history by date
        const grouped = groupHistoryByDate(history);
        
        // Render grouped history
        for (const [date, items] of Object.entries(grouped)) {
            // Date header
            const dateHeader = document.createElement('div');
            dateHeader.style.cssText = 'padding: 12px 16px; font-weight: 600; font-size: 14px; color: #1d1d1f; background: #f5f5f7; border-bottom: 1px solid #d2d2d7;';
            dateHeader.textContent = date;
            historyList.appendChild(dateHeader);
            
            // History items
            items.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.style.cssText = 'padding: 12px 16px; border-bottom: 1px solid #f5f5f7; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: background 0.2s;';
                itemEl.addEventListener('mouseenter', () => {
                    itemEl.style.background = '#f5f5f7';
                });
                itemEl.addEventListener('mouseleave', () => {
                    itemEl.style.background = 'transparent';
                });
                
                // Engine badge
                const engineBadge = document.createElement('span');
                engineBadge.style.cssText = 'font-size: 10px; padding: 2px 6px; border-radius: 4px; color: white; flex-shrink: 0;';
                engineBadge.textContent = item.engine || 'unknown';
                
                // Set engine color
                const engineColors = {
                    'firefox': '#FF9500',
                    'chromium': '#007AFF',
                    'tor': '#5856D6',
                    'prism': '#34C759'
                };
                engineBadge.style.background = engineColors[item.engine] || '#86868b';
                
                // Content
                const contentEl = document.createElement('div');
                contentEl.style.cssText = 'flex: 1; min-width: 0;';
                
                const titleEl = document.createElement('div');
                titleEl.style.cssText = 'font-size: 14px; font-weight: 500; color: #1d1d1f; margin-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
                titleEl.textContent = item.title;
                
                const urlEl = document.createElement('div');
                urlEl.style.cssText = 'font-size: 12px; color: #86868b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
                urlEl.textContent = item.url;
                
                contentEl.appendChild(titleEl);
                contentEl.appendChild(urlEl);
                
                // Time and visit count
                const metaEl = document.createElement('div');
                metaEl.style.cssText = 'text-align: right; font-size: 12px; color: #86868b; flex-shrink: 0;';
                const timeStr = formatTime(item.timestamp);
                const visitStr = item.visitCount > 1 ? `${item.visitCount} visits` : '1 visit';
                metaEl.innerHTML = `${timeStr}<br>${visitStr}`;
                
                itemEl.appendChild(engineBadge);
                itemEl.appendChild(contentEl);
                itemEl.appendChild(metaEl);
                
                // Click to navigate
                itemEl.addEventListener('click', async () => {
                    historyModal.classList.remove('active');
                    await navigateTo(item.url);
                });
                
                historyList.appendChild(itemEl);
            });
        }
        
    } catch (error) {
        console.error('Failed to load history:', error);
        historyList.innerHTML = `<div style="text-align: center; padding: 40px; color: #FF3B30;">Failed to load history: ${error.message}</div>`;
    }
}

function groupHistoryByDate(history) {
    const groups = {};
    const now = new Date();
    
    history.forEach(item => {
        const itemDate = new Date(item.timestamp);
        const daysDiff = Math.floor((now - itemDate) / (1000 * 60 * 60 * 24));
        
        let label;
        if (daysDiff === 0) {
            label = 'Today';
        } else if (daysDiff === 1) {
            label = 'Yesterday';
        } else if (daysDiff < 7) {
            label = `${daysDiff} days ago`;
        } else {
            label = itemDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        
        if (!groups[label]) {
            groups[label] = [];
        }
        groups[label].push(item);
    });
    
    return groups;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

// ===== DEPENDENCY STATUS =====

function showDependencyStatus(result) {
    const modal = document.getElementById('dependencyModal');
    const statusEl = document.getElementById('dependencyStatus');
    const actionsEl = document.getElementById('dependencyActions');
    
    if (!modal || !statusEl) return;
    
    const { dependencies, allInstalled } = result;
    
    let html = '<div style="margin-bottom: 16px;">';
    
    // Summary
    if (allInstalled) {
        html += `
            <div style="text-align: center; padding: 20px; background: #e8f5e9; border-radius: 8px; margin-bottom: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">✅</div>
                <div style="font-weight: 600; color: #2e7d32;">All Required Dependencies Installed!</div>
                <div style="font-size: 13px; color: #66bb6a; margin-top: 4px;">Prism Browser is fully operational</div>
            </div>
        `;
    } else {
        html += `
            <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px; margin-bottom: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">⚠️</div>
                <div style="font-weight: 600; color: #856404;">Some Dependencies Missing</div>
                <div style="font-size: 13px; color: #856404; margin-top: 4px;">Install missing dependencies for full functionality</div>
            </div>
        `;
    }
    
    html += '</div>';
    
    // Dependency list
    html += '<div style="display: flex; flex-direction: column; gap: 12px;">';
    
    for (const [name, dep] of Object.entries(dependencies)) {
        const status = dep.installed ? '✅' : '❌';
        const statusColor = dep.installed ? '#34C759' : '#FF3B30';
        const statusText = dep.installed ? 'Installed' : 'Not Installed';
        const required = dep.required ? 'REQUIRED' : 'OPTIONAL';
        const requiredColor = dep.required ? '#FF3B30' : '#86868b';
        
        html += `
            <div style="padding: 16px; background: #f5f5f7; border-radius: 8px; border-left: 4px solid ${statusColor};">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <div>
                        <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">
                            ${status} ${name.toUpperCase()}
                            <span style="font-size: 11px; padding: 2px 6px; background: ${dep.installed ? '#e8f5e9' : '#ffebee'}; color: ${statusColor}; border-radius: 4px; margin-left: 8px;">${statusText}</span>
                            <span style="font-size: 11px; padding: 2px 6px; background: white; color: ${requiredColor}; border-radius: 4px; margin-left: 4px;">${required}</span>
                        </div>
                        ${dep.version ? `<div style="font-size: 13px; color: #86868b;">Version: ${dep.version}</div>` : ''}
                        ${dep.path && dep.path !== 'Built-in (Electron)' ? `<div style="font-size: 12px; color: #86868b; margin-top: 4px;">📍 ${dep.path}</div>` : ''}
                    </div>
                </div>
                
                ${!dep.installed ? `
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #d2d2d7;">
                        <div style="font-size: 13px; margin-bottom: 8px;">
                            ${getDepDescription(name)}
                        </div>
                        <button onclick="installDependency('${name}')" style="padding: 8px 16px; background: #007AFF; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500;">
                            📥 Install ${name.toUpperCase()}
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    html += '</div>';
    
    statusEl.innerHTML = html;
    
    // Show actions
    if (actionsEl) {
        actionsEl.style.display = 'block';
    }
    
    // Show modal if there are missing dependencies
    if (!allInstalled) {
        modal.style.display = 'block';
        modal.classList.add('active');
    } else {
        // Auto-close after 3 seconds if all installed
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }, 3000);
    }
}

function getDepDescription(name) {
    const descriptions = {
        php: '🖥️ <strong>Required for backend features:</strong> HavenPay, AI services, advanced features',
        tor: '🔒 <strong>Enable anonymous browsing:</strong> Connect to Tor network for maximum privacy',
        firefox: '🦊 <strong>Native Firefox engine:</strong> Use real Firefox rendering for best compatibility',
        chromium: '⚡ <strong>Built-in engine:</strong> Already available via Electron'
    };
    return descriptions[name] || 'Optional dependency for enhanced functionality';
}

// Global function for install buttons
window.installDependency = async function(name) {
    const instructions = await ipcRenderer.invoke('dependencies:getInstructions', name);
    const confirmed = confirm(`Install ${name.toUpperCase()}?\n\n${instructions}\n\nClick OK to open the download page.`);
    
    if (confirmed) {
        await ipcRenderer.invoke('dependencies:openInstallPage', name);
        alert(`Opening ${name} download page in your browser.\n\nAfter installing, click "Recheck Dependencies" below.`);
    }
};

// Start the app
if (!isInitialized) {
    init();
    isInitialized = true;
}

