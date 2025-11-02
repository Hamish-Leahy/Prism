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
        this.switchTimeout = null;
        this.currentSwitchRequestId = 0; // Track current switch request to ignore stale results
        
        // DOM element cache for performance
        this.tabElementCache = new Map();
        
        // DOM elements
        this.tabBar = document.getElementById('tabBar');
        this.newTabBtn = document.getElementById('newTabBtn');
        
        // Throttle tab switching to prevent rapid clicks (optimized for 120Hz)
        this.lastSwitchTime = 0;
        this.switchThrottleMs = 16; // ~60fps minimum, allows faster switching on high refresh rate displays
        
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
        // Throttle rapid clicks
        const now = Date.now();
        if (now - this.lastSwitchTime < this.switchThrottleMs) {
            return;
        }
        this.lastSwitchTime = now;
        
        // If switching to same tab, ignore
        if (this.activeTabId === tabId) {
            return;
        }
        
        // Cancel any previous switch operation
        if (this.switchTimeout) {
            clearTimeout(this.switchTimeout);
            this.switchTimeout = null;
        }
        
        // Increment switch request ID to invalidate any pending switch operations
        const switchRequestId = ++this.currentSwitchRequestId;
        
        this.isSwitchingTab = true;
        
        const tab = this.tabs.find(t => t.id === tabId);
        if (!tab) {
            console.log('Tab not found:', tabId);
            this.isSwitchingTab = false;
            return;
        }
        
        // Set timeout to prevent getting stuck (especially with heavy sites like YouTube)
        this.switchTimeout = setTimeout(() => {
            // Only reset if this is still the current switch request
            if (switchRequestId === this.currentSwitchRequestId) {
                console.warn('Tab switch timeout, forcing reset');
                this.isSwitchingTab = false;
                this.switchTimeout = null;
            }
        }, 3000);
        
        // Update active tab and UI IMMEDIATELY (non-blocking)
        this.activeTabId = tabId;
        this.updateTabUI();
        
        // Hide start page immediately
        const startPage = document.getElementById('startPage');
        if (startPage) {
            startPage.classList.add('hidden');
        }
        
        // Hide other tabs asynchronously (don't wait)
        // This prevents blocking, especially with heavy sites like YouTube
        const hidePromises = [];
        this.tabs.forEach(t => {
            if (t.id !== tabId && t.visible && t.view) {
                // Don't await - just fire and forget for performance
                hidePromises.push(
                    ipcRenderer.invoke('engine:hideTab', t.id).catch(err => {
                        console.warn('Failed to hide tab:', t.id, err);
                    }).then(() => {
                        // Only update visibility if this is still the current switch
                        if (switchRequestId === this.currentSwitchRequestId) {
                            t.visible = false;
                        }
                    })
                );
            }
        });
        
        // Handle tab content - show new tab FIRST, then hide others
        // This makes switching feel instant
        try {
            // Check if this switch is still valid before proceeding
            if (switchRequestId !== this.currentSwitchRequestId) {
                console.log('Switch canceled - newer switch in progress');
                return;
            }
            
            if (tab.url && tab.view) {
                // Tab has content and view - show it immediately
                // OPTIMIZATION: If tab is already visible, showTabContent will handle it efficiently
                await this.showTabContent(tab, switchRequestId);
            } else if (tab.url && !tab.view) {
                // Tab has URL but no view yet - try to create view
                try {
                    await this.createEngineTab(tabId);
                    
                    // Check again after async operation
                    if (switchRequestId !== this.currentSwitchRequestId) {
                        console.log('Switch canceled - newer switch in progress');
                        return;
                    }
                    
                    const updatedTab = this.tabs.find(t => t.id === tabId);
                    if (updatedTab && updatedTab.view) {
                        await this.showTabContent(updatedTab, switchRequestId);
                    } else {
                        if (switchRequestId === this.currentSwitchRequestId) {
                            this.showStartPage();
                        }
                    }
                } catch (error) {
                    console.error('Failed to create view for tab:', error);
                    if (switchRequestId === this.currentSwitchRequestId) {
                        this.showStartPage();
                    }
                }
            } else {
                // Tab is empty - show start page
                if (switchRequestId === this.currentSwitchRequestId) {
                    this.showStartPage();
                }
            }
        } catch (error) {
            console.error('Error during tab switch:', error);
            // Fallback: show start page only if this is still the current switch
            if (switchRequestId === this.currentSwitchRequestId) {
                this.showStartPage();
            }
        }
        
        // Wait for hide operations in background (don't block)
        Promise.all(hidePromises).catch(err => {
            console.warn('Some tabs failed to hide:', err);
        }).finally(() => {
            // Clear switching flag after a short delay, but only if this is still the current switch
            setTimeout(() => {
                if (switchRequestId === this.currentSwitchRequestId) {
                    this.isSwitchingTab = false;
                    if (this.switchTimeout) {
                        clearTimeout(this.switchTimeout);
                        this.switchTimeout = null;
                    }
                }
            }, 100);
        });
    }
    
    updateTabUI() {
        // Update tab bar active states using cache for performance
        this.tabElementCache.forEach((tabEl, tabId) => {
            if (tabEl && tabEl.parentNode) {
                tabEl.classList.toggle('active', tabId === this.activeTabId);
            }
        });
        
        // Fallback: if cache misses, query DOM (should be rare)
        if (this.tabBar) {
            const allTabs = this.tabBar.querySelectorAll('.tab');
            allTabs.forEach(tabEl => {
                const tabId = tabEl.dataset.tabId;
                if (tabId && !this.tabElementCache.has(tabId)) {
                    this.tabElementCache.set(tabId, tabEl);
                }
                tabEl.classList.toggle('active', tabId === this.activeTabId);
            });
        }
        
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
    
    async showTabContent(tab, switchRequestId = null) {
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
            try {
                // OPTIMIZATION: If tab is already visible, skip IPC call for better performance
                if (tab.visible && (!switchRequestId || switchRequestId === this.currentSwitchRequestId)) {
                    // Tab is already shown, just update UI
                    const addressBar = document.getElementById('addressBar');
                    if (addressBar && tab.url) {
                        addressBar.value = tab.url;
                    }
                    return; // Early return - no need to show already visible tab
                }
                
                // Use Promise.race with timeout for heavy sites like YouTube
                // This prevents tab switching from getting stuck
                const showPromise = ipcRenderer.invoke('engine:showTab', tab.id);
                const timeoutPromise = new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('Show tab timeout')), 2000)
                );
                
                await Promise.race([showPromise, timeoutPromise]);
                
                // Only update if this is still the current switch request
                if (!switchRequestId || switchRequestId === this.currentSwitchRequestId) {
                    tab.visible = true;
                    
                    // Update address bar with tab's URL
                    const addressBar = document.getElementById('addressBar');
                    if (addressBar && tab.url) {
                        addressBar.value = tab.url;
                    }
                }
            } catch (error) {
                // Only update if this is still the current switch request
                if (!switchRequestId || switchRequestId === this.currentSwitchRequestId) {
                    console.warn('Show tab had issues (may still work):', error.message);
                    // Mark as visible anyway - the tab might still be showing
                    // This is especially important for heavy sites like YouTube
                    tab.visible = true;
                    
                    // Update address bar even if show failed
                    const addressBar = document.getElementById('addressBar');
                    if (addressBar && tab.url) {
                        addressBar.value = tab.url;
                    }
                    
                    // Only show start page if it's a critical error
                    if (!error.message.includes('timeout')) {
                        // For non-timeout errors, try fallback
                        try {
                            await this.createEngineTab(tab.id);
                            
                            // Check again after async operation
                            if (switchRequestId && switchRequestId !== this.currentSwitchRequestId) {
                                return; // Canceled by newer switch
                            }
                            
                            const updatedTab = this.tabs.find(t => t.id === tab.id);
                            if (updatedTab && updatedTab.view) {
                                await ipcRenderer.invoke('engine:navigate', tab.id, tab.url);
                                await ipcRenderer.invoke('engine:showTab', tab.id).catch(() => {});
                                if (switchRequestId === null || switchRequestId === this.currentSwitchRequestId) {
                                    updatedTab.visible = true;
                                }
                            }
                        } catch (recreateError) {
                            console.error('Failed to recreate tab view:', recreateError);
                        }
                    }
                }
            }
        } else {
            if (!switchRequestId || switchRequestId === this.currentSwitchRequestId) {
                this.showStartPage(); // No view yet
            }
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
        
        // Remove from cache immediately
        const tabElement = this.tabElementCache.get(tabId);
        if (tabElement && tabElement.parentNode) {
            tabElement.remove();
        }
        this.tabElementCache.delete(tabId);
        
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
        
        // Render tabs efficiently
        this.renderTabs();
    }
    
    renderTabs() {
        if (!this.tabBar) return;
        
        // Use requestAnimationFrame for smooth rendering (works with any refresh rate)
        if (this.renderAnimationFrame) {
            cancelAnimationFrame(this.renderAnimationFrame);
        }
        
        // Optimize for high refresh rate displays (120Hz, 144Hz, etc.)
        this.renderAnimationFrame = requestAnimationFrame(() => {
            this._renderTabsSync();
            this.renderAnimationFrame = null;
        });
    }
    
    _renderTabsSync() {
        if (!this.tabBar) return;
        
        const currentTabIds = new Set(this.tabs.map(tab => tab.id));
        
        // Remove tabs from cache and DOM that no longer exist
        for (const [tabId, tabElement] of this.tabElementCache.entries()) {
            if (!currentTabIds.has(tabId)) {
                if (tabElement && tabElement.parentNode) {
                    tabElement.remove();
                }
                this.tabElementCache.delete(tabId);
            }
        }
        
        // Update or create tabs efficiently
        this.tabs.forEach((tab, index) => {
            let tabElement = this.tabElementCache.get(tab.id);
            
            if (!tabElement || !tabElement.parentNode) {
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
                if (closeButton) {
                    closeButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.closeTab(tab.id);
                    });
                }
                
                // Insert before the new tab button
                if (this.newTabBtn && this.newTabBtn.parentNode) {
                    this.tabBar.insertBefore(tabElement, this.newTabBtn);
                } else {
                    this.tabBar.appendChild(tabElement);
                }
                
                // Cache the element
                this.tabElementCache.set(tab.id, tabElement);
            }
            
            // Update tab content efficiently (only if changed)
            const titleEl = tabElement.querySelector('.tab-title');
            const loadingEl = tabElement.querySelector('.tab-loading');
            const indicatorEl = tabElement.querySelector('.tab-engine-indicator');
            
            // Only update if content changed (performance optimization)
            if (titleEl && titleEl.textContent !== (tab.title || 'New Tab')) {
                titleEl.textContent = tab.title || 'New Tab';
            }
            
            if (loadingEl) {
                const isLoading = tab.isLoading;
                const hasLoadingClass = loadingEl.classList.contains('loading');
                if (isLoading !== hasLoadingClass) {
                    loadingEl.className = `tab-loading ${isLoading ? 'loading' : ''}`;
                }
            }
            
            if (indicatorEl) {
                const engine = tab.engine || 'firefox';
                const currentEngine = indicatorEl.className.replace('tab-engine-indicator ', '');
                if (currentEngine !== engine) {
                    indicatorEl.className = 'tab-engine-indicator';
                    indicatorEl.classList.add(engine);
                }
            }
            
            // Update active state
            const isActive = tab.id === this.activeTabId;
            if (isActive) {
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
