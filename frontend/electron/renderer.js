/**
 * Main Renderer - Simplified and modular
 * Uses separate modules for different functionality
 */

const { ipcRenderer } = require('electron');

// Global managers
let tabManager;
let navigationManager;
let uiManager;

// Initialize the application
async function init() {
    console.log('🚀 Initializing Prism Browser...');
    
    try {
        // Initialize managers
        tabManager = new TabManager();
        navigationManager = new NavigationManager(tabManager);
        uiManager = new UIManager();
        
        // Make managers globally available
        window.tabManager = tabManager;
        window.navigationManager = navigationManager;
        window.uiManager = uiManager;
        
        // Setup IPC listeners
        setupIPCListeners();
        
        // Wait for engines to be ready
        console.log('⏳ Waiting for engines to initialize...');
        
    } catch (error) {
        console.error('❌ Failed to initialize:', error);
    }
}

// Setup IPC event listeners
function setupIPCListeners() {
    // Listen for engines ready event
    ipcRenderer.once('engines-ready', async () => {
        console.log('✅ Engines ready');
        // Create initial tab
        tabManager.createNewTab();
    });
    
    // Listen for engine events
    ipcRenderer.on('engine-event', (event, { event: eventName, data }) => {
        handleEngineEvent(eventName, data);
    });
    
    // Listen for network errors
    ipcRenderer.on('network-error', (event, { tabId, error, errorCode }) => {
        console.log('Network error:', error, 'for tab:', tabId);
        uiManager.showNoInternetPage();
    });
    
    // Menu shortcuts
    ipcRenderer.on('new-tab', () => tabManager.createNewTab());
    ipcRenderer.on('close-tab', () => {
        const activeTab = tabManager.getActiveTab();
        if (activeTab) {
            tabManager.closeTab(activeTab.id);
        }
    });
    ipcRenderer.on('navigate-back', () => navigationManager.goBack());
    ipcRenderer.on('navigate-forward', () => navigationManager.goForward());
    ipcRenderer.on('navigate-refresh', () => navigationManager.refresh());
    ipcRenderer.on('navigate-home', () => {
        uiManager.showStartPage();
        navigationManager.updateAddressBar('');
    });
}

// Handle engine events
function handleEngineEvent(eventName, data) {
    const tab = tabManager.getAllTabs().find(t => t.id === data.tabId);
    if (!tab) return;
    
    switch (eventName) {
        case 'title-updated':
            tab.title = data.title || 'New Tab';
            tabManager.renderTabs();
            break;
            
        case 'navigation':
        case 'url-updated':
            tab.url = data.url;
            if (tab.id === tabManager.activeTabId) {
                navigationManager.updateAddressBar(data.url);
                uiManager.updateSecurityIndicator(data.url);
            }
            break;
            
        case 'loading-start':
            tab.isLoading = true;
            tabManager.renderTabs();
            break;
            
        case 'loading-stop':
            tab.isLoading = false;
            tabManager.renderTabs();
            
            // Add to history (skip Tor tabs)
            if (data.url && data.url !== 'about:blank' && 
                !data.url.startsWith('prism://') && tab.engine !== 'tor') {
                ipcRenderer.invoke('data:addToHistory', data.url, data.title || tab.title, tab.engine)
                    .catch(err => console.error('Failed to add to history:', err));
            }
            break;
            
        case 'favicon-updated':
            // TODO: Handle favicon
            break;
    }
}

// Start the application
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
