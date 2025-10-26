/**
 * TabManager - Handles tab creation, switching, and management
 * Simplified and focused on core tab functionality
 */

class TabManager {
    constructor() {
        this.tabs = [];
        this.activeTabId = null;
        this.tabIdCounter = 0;
        this.isCreatingTab = false;
        this.isSwitchingTab = false;
        
        // DOM elements
        this.tabBar = document.getElementById('tabBar');
        this.newTabBtn = document.getElementById('newTabBtn');
        
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // New tab button
        if (this.newTabBtn) {
            this.newTabBtn.addEventListener('click', () => {
                this.createNewTab();
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 't') {
                e.preventDefault();
                this.createNewTab();
            }
        });
    }
    
    createNewTab() {
        if (this.isCreatingTab) {
            console.log('Tab creation already in progress, ignoring...');
            return;
        }
        
        this.isCreatingTab = true;
        
        const tabId = `tab-${this.tabIdCounter++}`;
        const tab = {
            id: tabId,
            title: 'New Tab',
            url: '',
            engine: 'firefox', // Default engine
            isLoading: false,
            visible: false,
            view: null
        };
        
        this.tabs.push(tab);
        this.activeTabId = tabId;
        
        // Update UI immediately
        this.renderTabs();
        
        // Show start page immediately
        this.showStartPage();
        
        // Create engine tab in background (non-blocking)
        this.createEngineTab(tabId);
        
        this.isCreatingTab = false;
        
        console.log('✅ New tab created:', tabId);
    }
    
    async createEngineTab(tabId) {
        try {
            const result = await ipcRenderer.invoke('engine:createTab', tabId, 'firefox', {});
            const tab = this.tabs.find(t => t.id === tabId);
            if (tab) {
                tab.view = result.view;
                console.log('✅ Engine tab created:', tabId);
            }
        } catch (error) {
            console.error('❌ Failed to create engine tab:', error);
            // Tab still works with start page functionality
        }
    }
    
    switchToTab(tabId) {
        if (this.isSwitchingTab) {
            console.log('Tab switching already in progress, skipping...');
            return;
        }
        
        this.isSwitchingTab = true;
        
        const tab = this.tabs.find(t => t.id === tabId);
        if (!tab) {
            this.isSwitchingTab = false;
            return;
        }
        
        this.activeTabId = tabId;
        
        // Update UI immediately
        this.updateTabUI();
        
        // Handle tab content
        if (tab.url) {
            this.showTabContent(tab);
        } else {
            this.showStartPage();
        }
        
        this.isSwitchingTab = false;
    }
    
    updateTabUI() {
        // Update tab bar active states
        const allTabs = this.tabBar.querySelectorAll('.tab');
        allTabs.forEach(tabEl => {
            tabEl.classList.toggle('active', tabEl.dataset.tabId === this.activeTabId);
        });
        
        // Update address bar
        const tab = this.tabs.find(t => t.id === this.activeTabId);
        if (tab) {
            const addressBar = document.getElementById('addressBar');
            if (addressBar) {
                addressBar.value = tab.url || '';
            }
        }
    }
    
    showTabContent(tab) {
        // Hide start page
        const startPage = document.getElementById('startPage');
        if (startPage) startPage.classList.add('hidden');
        
        // Show tab content if view exists
        if (tab.view) {
            ipcRenderer.invoke('engine:showTab', tab.id)
                .then(() => {
                    tab.visible = true;
                })
                .catch(error => {
                    console.error('Failed to show tab:', error);
                    this.showStartPage(); // Fallback
                });
        } else {
            this.showStartPage(); // No view yet
        }
    }
    
    showStartPage() {
        // Hide all web content
        this.tabs.forEach(t => {
            if (t.view && t.visible) {
                ipcRenderer.invoke('engine:hideTab', t.id);
                t.visible = false;
            }
        });
        
        // Show start page
        const startPage = document.getElementById('startPage');
        if (startPage) {
            startPage.classList.remove('hidden');
        }
        
        // Clear address bar
        const addressBar = document.getElementById('addressBar');
        if (addressBar) {
            addressBar.value = '';
        }
    }
    
    closeTab(tabId) {
        const index = this.tabs.findIndex(t => t.id === tabId);
        if (index === -1) return;
        
        const tab = this.tabs[index];
        
        // Close engine tab
        if (tab.view) {
            ipcRenderer.invoke('engine:closeTab', tab.id).catch(err => {
                console.error('Failed to close engine tab:', err);
            });
        }
        
        // Remove from tabs array
        this.tabs.splice(index, 1);
        
        if (this.tabs.length === 0) {
            // No tabs left, show start page
            this.showStartPage();
            this.activeTabId = null;
        } else if (this.activeTabId === tabId) {
            // Switch to another tab
            const newActiveIndex = Math.min(index, this.tabs.length - 1);
            const newActiveTab = this.tabs[newActiveIndex];
            if (newActiveTab) {
                this.switchToTab(newActiveTab.id);
            }
        }
        
        this.renderTabs();
    }
    
    renderTabs() {
        if (!this.tabBar) return;
        
        // Clear existing tabs
        const existingTabs = this.tabBar.querySelectorAll('.tab');
        existingTabs.forEach(tabEl => tabEl.remove());
        
        // Create tab elements
        this.tabs.forEach(tab => {
            const tabElement = document.createElement('div');
            tabElement.className = `tab ${tab.id === this.activeTabId ? 'active' : ''}`;
            tabElement.dataset.tabId = tab.id;
            
            tabElement.innerHTML = `
                <div class="tab-favicon">
                    <div class="favicon-placeholder"></div>
                </div>
                <div class="tab-title">${tab.title}</div>
                <div class="tab-close">×</div>
            `;
            
            // Add event listeners
            tabElement.addEventListener('click', (e) => {
                if (!e.target.classList.contains('tab-close')) {
                    this.switchToTab(tab.id);
                }
            });
            
            const closeButton = tabElement.querySelector('.tab-close');
            closeButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.closeTab(tab.id);
            });
            
            // Insert before new tab button
            this.tabBar.insertBefore(tabElement, this.newTabBtn);
        });
    }
    
    getActiveTab() {
        return this.tabs.find(t => t.id === this.activeTabId);
    }
    
    getAllTabs() {
        return this.tabs;
    }
}

// Export for use in main renderer
window.TabManager = TabManager;
