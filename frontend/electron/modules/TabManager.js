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
        
        // CRITICAL: Hide ALL other tabs first to prevent overlay
        this.tabs.forEach(otherTab => {
            if (otherTab.id !== tabId && otherTab.visible && otherTab.view) {
                ipcRenderer.invoke('engine:hideTab', otherTab.id);
                otherTab.visible = false;
            }
        });
        
        // Update UI immediately
        this.renderTabs();
        this.updateEngineBadge(tab.engine);
        
        // Show start page immediately
        this.showStartPage();
        
        // Create engine tab in background (non-blocking)
        this.createEngineTab(tabId);
        
        this.isCreatingTab = false;
        
        console.log('✅ New tab created:', tabId);
    }
    
    async createEngineTab(tabId) {
        try {
            const tab = this.tabs.find(t => t.id === tabId);
            if (!tab) {
                console.error('Tab not found when creating engine tab:', tabId);
                return;
            }
            
            const result = await ipcRenderer.invoke('engine:createTab', tabId, tab.engine || 'firefox', {});
            if (tab) {
                tab.view = result.view;
                console.log('✅ Engine tab created:', tabId);
                
                // If tab has a URL, navigate to it immediately
                if (tab.url) {
                    try {
                        await ipcRenderer.invoke('engine:navigate', tabId, tab.url);
                        console.log('✅ Navigated restored tab to:', tab.url);
                    } catch (navError) {
                        console.error('Failed to navigate restored tab:', navError);
                    }
                }
            }
        } catch (error) {
            console.error('❌ Failed to create engine tab:', error);
            // Tab still works with start page functionality
        }
    }
    
    async switchToTab(tabId) {
        if (this.isSwitchingTab) {
            console.log('Tab switching already in progress, skipping...');
            return;
        }
        
        this.isSwitchingTab = true;
        
        const tab = this.tabs.find(t => t.id === tabId);
        if (!tab) {
            console.log('Tab not found:', tabId);
            this.isSwitchingTab = false;
            return;
        }
        
        console.log('Switching to tab:', tabId, 'URL:', tab.url, 'Has view:', !!tab.view, 'Visible:', tab.visible);
        
        this.activeTabId = tabId;
        
        // Update UI immediately
        this.updateTabUI();
        
        // CRITICAL: Hide ALL other tabs first
        const hidePromises = [];
        this.tabs.forEach(t => {
            if (t.id !== tabId && t.visible && t.view) {
                console.log('Hiding other tab:', t.id);
                hidePromises.push(
                    ipcRenderer.invoke('engine:hideTab', t.id).then(() => {
                        t.visible = false;
                    })
                );
            }
        });
        await Promise.all(hidePromises);
        
        // Hide start page immediately
        const startPage = document.getElementById('startPage');
        if (startPage) {
            startPage.classList.add('hidden');
        }
        
        // Handle tab content based on what it has
        if (tab.url && tab.view) {
            // Tab has content and view - show it
            console.log('Tab has content and view, showing content');
            await this.showTabContent(tab);
        } else if (tab.url && !tab.view) {
            // Tab has URL but no view yet - try to create view or show start page
            console.log('Tab has URL but no view, attempting to create view');
            try {
                await this.createEngineTab(tabId);
                // Check again after creating view
                const updatedTab = this.tabs.find(t => t.id === tabId);
                if (updatedTab && updatedTab.view) {
                    await this.showTabContent(updatedTab);
                } else {
                    this.showStartPage();
                }
            } catch (error) {
                console.error('Failed to create view for tab:', error);
                this.showStartPage();
            }
        } else {
            // Tab is empty - show start page
            console.log('Tab is empty, showing start page');
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
        
        // Update address bar and engine badge
        const tab = this.tabs.find(t => t.id === this.activeTabId);
        if (tab) {
            const addressBar = document.getElementById('addressBar');
            if (addressBar) {
                addressBar.value = tab.url || '';
            }
            
            // Update engine badge
            this.updateEngineBadge(tab.engine || 'firefox');
        }
    }
    
    updateEngineBadge(engine) {
        const engineBadge = document.getElementById('engineBadge');
        const engineSelector = document.getElementById('engineSelector');
        
        if (engineBadge) {
            const engineNames = {
                'prism': 'Prism',
                'chromium': 'Chromium',
                'firefox': 'Firefox',
                'tor': 'Tor'
            };
            
            engineBadge.textContent = engineNames[engine] || 'Firefox';
            engineBadge.className = 'engine-badge ' + engine;
        }
        
        if (engineSelector && engineSelector.value !== engine) {
            engineSelector.value = engine;
        }
    }
    
    async showTabContent(tab) {
        console.log('Showing tab content for:', tab.id, 'URL:', tab.url, 'Has view:', !!tab.view);
        
        // Hide start page immediately
        const startPage = document.getElementById('startPage');
        if (startPage) {
            startPage.classList.add('hidden');
        }
        
        // Hide no-internet page too
        const noInternetPage = document.getElementById('noInternetPage');
        if (noInternetPage) {
            noInternetPage.classList.add('hidden');
        }
        
        // Show tab content if view exists
        if (tab.view) {
            console.log('Calling engine:showTab for:', tab.id);
            try {
                await ipcRenderer.invoke('engine:showTab', tab.id);
                console.log('Successfully showed tab:', tab.id);
                tab.visible = true;
                
                // Update address bar with tab's URL
                const addressBar = document.getElementById('addressBar');
                if (addressBar && tab.url) {
                    addressBar.value = tab.url;
                }
            } catch (error) {
                console.error('Failed to show tab:', error);
                // Fallback: try to recreate view or show start page
                if (tab.url) {
                    try {
                        await this.createEngineTab(tab.id);
                        const updatedTab = this.tabs.find(t => t.id === tab.id);
                        if (updatedTab && updatedTab.view) {
                            await ipcRenderer.invoke('engine:navigate', tab.id, tab.url);
                            await ipcRenderer.invoke('engine:showTab', tab.id);
                            updatedTab.visible = true;
                        } else {
                            this.showStartPage();
                        }
                    } catch (recreateError) {
                        console.error('Failed to recreate tab view:', recreateError);
                        this.showStartPage();
                    }
                } else {
                    this.showStartPage();
                }
            }
        } else {
            console.log('No view for tab:', tab.id, 'showing start page');
            this.showStartPage(); // No view yet
        }
    }
    
    showStartPage() {
        // Hide ALL web content first
        this.tabs.forEach(t => {
            if (t.view && t.visible) {
                ipcRenderer.invoke('engine:hideTab', t.id);
                t.visible = false;
            }
        });
        
        // Force hide all views in Electron
        ipcRenderer.invoke('engine:hideAllViews').then(() => {
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
        }).catch(error => {
            console.error('Failed to hide all views:', error);
            // Still show start page even if hiding fails
            const startPage = document.getElementById('startPage');
            if (startPage) {
                startPage.classList.remove('hidden');
            }
        });
    }
    
    async closeTab(tabId) {
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
                await this.switchToTab(newActiveTab.id);
            }
        }
        
        this.renderTabs();
    }
    
    renderTabs() {
        if (!this.tabBar) return;
        
        // Get existing tab elements
        const existingTabs = Array.from(this.tabBar.querySelectorAll('.tab'));
        const existingTabIds = new Set(existingTabs.map(el => el.dataset.tabId));
        const currentTabIds = new Set(this.tabs.map(tab => tab.id));
        
        // Remove tabs that no longer exist
        existingTabs.forEach(tabEl => {
            if (!currentTabIds.has(tabEl.dataset.tabId)) {
                tabEl.remove();
            }
        });
        
        // Update or create tabs
        this.tabs.forEach((tab, index) => {
            let tabElement = this.tabBar.querySelector(`[data-tab-id="${tab.id}"]`);
            
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
                tabElement.addEventListener('click', async (e) => {
                    if (!e.target.classList.contains('tab-close')) {
                        await this.switchToTab(tab.id);
                    }
                });
                
                const closeButton = tabElement.querySelector('.tab-close');
                closeButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.closeTab(tab.id);
                });
                
                // Mark as having listeners to prevent duplicates
                tabElement.dataset.listenersAdded = 'true';
                
                // Insert before the new tab button
                this.tabBar.insertBefore(tabElement, this.newTabBtn);
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
                // Remove all engine classes first
                indicatorEl.className = 'tab-engine-indicator';
                // Add the correct engine class
                const engine = tab.engine || 'firefox';
                indicatorEl.classList.add(engine);
            }
            
            // Update active state
            if (tab.id === this.activeTabId) {
                tabElement.classList.add('active');
            } else {
                tabElement.classList.remove('active');
            }
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
