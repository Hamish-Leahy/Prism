/**
 * NavigationManager - Handles URL navigation and address bar
 * Simplified navigation without complex loading states
 */

class NavigationManager {
    constructor(tabManager) {
        this.tabManager = tabManager;
        this.defaultEngine = 'firefox';
        
        // DOM elements
        this.addressBar = document.getElementById('addressBar');
        this.backBtn = document.getElementById('backBtn');
        this.forwardBtn = document.getElementById('forwardBtn');
        this.refreshBtn = document.getElementById('refreshBtn');
        
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Address bar navigation
        if (this.addressBar) {
            this.addressBar.addEventListener('keypress', async (e) => {
                if (e.key === 'Enter') {
                    await this.navigateTo(this.addressBar.value);
                }
            });
        }
        
        // Navigation buttons
        if (this.backBtn) {
            this.backBtn.addEventListener('click', () => this.goBack());
        }
        
        if (this.forwardBtn) {
            this.forwardBtn.addEventListener('click', () => this.goForward());
        }
        
        if (this.refreshBtn) {
            this.refreshBtn.addEventListener('click', () => this.refresh());
        }
    }
    
    async navigateTo(input) {
        if (!input) return;
        
        let url = input.trim();
        let tab = this.tabManager.getActiveTab();
        
        // Create new tab if none exists
        if (!tab) {
            this.tabManager.createNewTab();
            tab = this.tabManager.getActiveTab();
            if (!tab) return;
        }
        
        // Handle prism:// protocol
        if (url.startsWith('prism://')) {
            this.handlePrismProtocol(url);
            return;
        }
        
        // Check if it's a URL or search query
        const isSearch = !url.includes('.') || url.includes(' ');
        
        if (isSearch) {
            // Convert search to URL
            url = this.convertSearchToUrl(url, tab.engine);
        } else if (!url.startsWith('http://') && !url.startsWith('https://')) {
            url = 'https://' + url;
        }
        
        // Update tab state
        tab.url = url;
        tab.isLoading = true;
        
        // Update UI
        this.addressBar.value = url;
        this.tabManager.renderTabs();
        
        // Navigate
        try {
            await ipcRenderer.invoke('engine:navigate', tab.id, url);
            await ipcRenderer.invoke('engine:showTab', tab.id);
            tab.visible = true;
            tab.isLoading = false;
        } catch (error) {
            console.error('Navigation failed:', error);
            tab.isLoading = false;
            tab.visible = false;
            
            // Show error or fallback
            if (this.isNetworkError(error)) {
                this.showNoInternetPage();
            } else {
                this.tabManager.showStartPage();
            }
        }
    }
    
    convertSearchToUrl(query, engine) {
        switch (engine) {
            case 'tor':
                return `https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/?q=${encodeURIComponent(query)}`;
            case 'firefox':
                return `https://duckduckgo.com/?q=${encodeURIComponent(query)}`;
            case 'chromium':
                return `https://www.google.com/search?q=${encodeURIComponent(query)}`;
            default:
                return `https://www.google.com/search?q=${encodeURIComponent(query)}`;
        }
    }
    
    isNetworkError(error) {
        return error.message && (
            error.message.includes('net::') ||
            error.message.includes('ERR_INTERNET_DISCONNECTED') ||
            error.message.includes('ERR_NETWORK_CHANGED') ||
            error.message.includes('ERR_CONNECTION_REFUSED') ||
            error.message.includes('ERR_NAME_NOT_RESOLVED')
        );
    }
    
    async goBack() {
        const tab = this.tabManager.getActiveTab();
        if (tab && tab.view) {
            try {
                await ipcRenderer.invoke('engine:goBack', tab.id);
            } catch (error) {
                console.error('Go back failed:', error);
            }
        }
    }
    
    async goForward() {
        const tab = this.tabManager.getActiveTab();
        if (tab && tab.view) {
            try {
                await ipcRenderer.invoke('engine:goForward', tab.id);
            } catch (error) {
                console.error('Go forward failed:', error);
            }
        }
    }
    
    async refresh() {
        const tab = this.tabManager.getActiveTab();
        if (tab && tab.url) {
            try {
                await ipcRenderer.invoke('engine:reload', tab.id);
            } catch (error) {
                console.error('Refresh failed:', error);
            }
        } else {
            // Show start page
            this.tabManager.showStartPage();
        }
    }
    
    handlePrismProtocol(url) {
        const prismUrl = new URL(url);
        const path = prismUrl.hostname;
        
        if (path === 'home') {
            this.tabManager.showStartPage();
        } else {
            console.log('Unknown Prism protocol:', path);
        }
    }
    
    showNoInternetPage() {
        const noInternetPage = document.getElementById('noInternetPage');
        const startPage = document.getElementById('startPage');
        
        if (noInternetPage) {
            noInternetPage.classList.remove('hidden');
        }
        if (startPage) {
            startPage.classList.add('hidden');
        }
    }
    
    hideNoInternetPage() {
        const noInternetPage = document.getElementById('noInternetPage');
        if (noInternetPage) {
            noInternetPage.classList.add('hidden');
        }
    }
    
    updateAddressBar(url) {
        if (this.addressBar) {
            this.addressBar.value = url || '';
        }
    }
}

// Export for use in main renderer
window.NavigationManager = NavigationManager;
