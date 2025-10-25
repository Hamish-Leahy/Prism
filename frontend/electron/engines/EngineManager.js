/**
 * Engine Manager - Coordinates all browser engines
 * Handles engine switching, tab management, and IPC communication
 */

const ChromiumEngine = require('./ChromiumEngine');
const FirefoxEngine = require('./FirefoxEngine');
const TorEngine = require('./TorEngine');
const PrismEngine = require('./PrismEngine');
const { ipcMain } = require('electron');

class EngineManager {
    constructor(mainWindow, extensionManager = null) {
        this.mainWindow = mainWindow;
        this.extensionManager = extensionManager;
        this.engines = new Map();
        this.tabs = new Map(); // tabId -> { engine, engineTabId }
        this.eventListeners = new Map();
        this.initialized = false;
        
        // Deep sleep management - DISABLED for now to prevent reloads
        this.maxActiveTabs = 999; // Effectively disable deep sleep
        this.tabAccessOrder = []; // LRU order for tab management
        this.sleepingTabs = new Map(); // tabId -> { url, title, engine, timestamp }
        
        // Advanced resource management
        this.resourceMonitor = {
            memoryThreshold: 1024 * 1024 * 1024, // 1GB memory threshold
            cpuThreshold: 80, // 80% CPU threshold
            lastCleanup: Date.now(),
            cleanupInterval: 30000, // 30 seconds
            heavySites: new Set(['youtube.com', 'netflix.com', 'twitch.tv', 'vimeo.com']),
            performanceMode: false
        };
        
        // Performance monitoring
        this.performanceStats = {
            totalMemory: 0,
            usedMemory: 0,
            cpuUsage: 0,
            activeTabs: 0,
            sleepingTabs: 0,
            lastUpdate: Date.now()
        };
        
        // Register IPC handlers immediately so they're available before initialization completes
        this.setupIPC();
    }

    async initialize() {
        try {
            console.log('🚀 Initializing Engine Manager...');
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            // Initialize all engines
            const chromium = new ChromiumEngine({ 
                mainWindow: this.mainWindow,
                eventHandler: (event, data) => this.handleEngineEvent(event, data)
            });
            
            const firefox = new FirefoxEngine({ 
                mainWindow: this.mainWindow,
                eventHandler: (event, data) => this.handleEngineEvent(event, data)
            });
            
            const tor = new TorEngine({ 
                mainWindow: this.mainWindow,
                eventHandler: (event, data) => this.handleEngineEvent(event, data)
                // NO extensionManager - Tor runs with no extensions for maximum privacy
            });
            
            const prism = new PrismEngine({ 
                mainWindow: this.mainWindow,
                backendUrl: 'http://localhost:8000',
                eventHandler: (event, data) => this.handleEngineEvent(event, data)
            });

            // Store engines immediately (before initialization)
            this.engines.set('chromium', chromium);
            this.engines.set('firefox', firefox);
            this.engines.set('tor', tor);
            this.engines.set('prism', prism);

            // Initialize engines one by one with detailed logging
            console.log('\n📦 Initializing Chromium Engine...');
            try {
                await chromium.initialize();
                console.log(`   ${chromium.ready ? '✅' : '❌'} Chromium: ${chromium.ready ? 'READY' : 'FAILED'}`);
            } catch (err) {
                console.error('   ❌ Chromium initialization error:', err.message);
            }

            console.log('\n🦊 Initializing Firefox Engine...');
            try {
                await firefox.initialize();
                console.log(`   ${firefox.ready ? '✅' : '❌'} Firefox: ${firefox.ready ? 'READY' : 'FAILED'}`);
            } catch (err) {
                console.error('   ❌ Firefox initialization error:', err.message);
            }

            console.log('\n🧅 Initializing Tor Engine...');
            try {
                await tor.initialize();
                console.log(`   ${tor.ready ? '✅' : '❌'} Tor: ${tor.ready ? 'READY' : 'FAILED'}`);
            } catch (err) {
                console.error('   ❌ Tor initialization error:', err.message);
            }

            console.log('\n🌈 Initializing Prism Engine...');
            try {
                await prism.initialize();
                console.log(`   ${prism.ready ? '✅' : '❌'} Prism: ${prism.ready ? 'READY' : 'FAILED'}`);
            } catch (err) {
                console.error('   ❌ Prism initialization error:', err.message);
            }

            this.initialized = true;
            
            console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            console.log('✅ Engine Manager initialized');
            console.log('📊 Engine Status Report:');
            for (const [name, engine] of this.engines.entries()) {
                const status = engine.ready ? '✅ READY' : '❌ NOT READY';
                console.log(`   ${status} - ${name.toUpperCase()}`);
            }
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
            
            return true;
        } catch (error) {
            console.error('❌ Engine Manager initialization failed:', error);
            return false;
        }
    }

    setupIPC() {
        // Only register handlers if they haven't been registered yet
        // This prevents "Attempted to register a second handler" errors
        const handlers = {
            'engine:createTab': async (event, tabId, engineName, options) => {
                // Wait for initialization if not ready yet
                if (!this.initialized) {
                    console.log('⏳ Waiting for engines to initialize...');
                    await this.waitForInitialization();
                }
                return await this.createTab(tabId, engineName, options);
            },
            'engine:closeTab': async (event, tabId) => {
                return await this.closeTab(tabId);
            },
            'engine:showTab': async (event, tabId) => {
                return await this.showTab(tabId);
            },
            'engine:hideTab': async (event, tabId) => {
                return await this.hideTab(tabId);
            },
            'engine:hideAllViews': async () => {
                return await this.hideAllViews();
            },
            'engine:hideOtherViews': async (event, currentTabId) => {
                return await this.hideOtherViews(currentTabId);
            },
            'engine:showActiveView': async () => {
                return await this.showActiveView();
            },
            'engine:navigate': async (event, tabId, url) => {
                return await this.navigate(tabId, url);
            },
            'engine:goBack': async (event, tabId) => {
                return await this.goBack(tabId);
            },
            'engine:goForward': async (event, tabId) => {
                return await this.goForward(tabId);
            },
            'engine:reload': async (event, tabId) => {
                return await this.reload(tabId);
            },
            'engine:stop': async (event, tabId) => {
                return await this.stop(tabId);
            },
            'engine:getTitle': async (event, tabId) => {
                return await this.getTitle(tabId);
            },
            'engine:getURL': async (event, tabId) => {
                return await this.getURL(tabId);
            },
            'engine:isTabSleeping': async (event, tabId) => {
                return this.isTabSleeping(tabId);
            },
            'engine:getSleepingTabInfo': async (event, tabId) => {
                return this.getSleepingTabInfo(tabId);
            },
            'engine:wakeTabFromSleep': async (event, tabId) => {
                return await this.wakeTabFromSleep(tabId);
            },
            'engine:optimizeHeavySite': async (event, tabId) => {
                return await this.optimizeHeavySiteTab(tabId);
            },
            'engine:canGoBack': async (event, tabId) => {
                return await this.canGoBack(tabId);
            },
            'engine:canGoForward': async (event, tabId) => {
                return await this.canGoForward(tabId);
            },
            'engine:isLoading': async (event, tabId) => {
                return await this.isLoading(tabId);
            },
            'engine:getInfo': async (event, engineName) => {
                const engine = this.engines.get(engineName);
                return engine ? engine.getInfo() : null;
            },
            'engine:getAllEngines': async () => {
                const engines = {};
                for (const [name, engine] of this.engines.entries()) {
                    engines[name] = engine.getInfo();
                }
                return engines;
            },
            'engine:switchTabEngine': async (event, tabId, newEngine) => {
                return await this.switchTabEngine(tabId, newEngine);
            }
        };

        // Register handlers only if they don't exist
        for (const [channel, handler] of Object.entries(handlers)) {
            try {
                ipcMain.handle(channel, handler);
            } catch (error) {
                // Handler already exists, remove it first and re-register
                if (error.message.includes('second handler')) {
                    ipcMain.removeHandler(channel);
                    ipcMain.handle(channel, handler);
                } else {
                    throw error;
                }
            }
        }

        console.log('✅ IPC handlers registered');
    }

    async waitForInitialization() {
        // Poll until initialized
        while (!this.initialized) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    async createTab(tabId, engineName, options = {}) {
        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        const engine = this.engines.get(engineName);
        if (!engine) {
            throw new Error('Engine not found: ' + engineName);
        }

        const result = await engine.createTab(tabId, options);
        
        this.tabs.set(tabId, {
            engine: engineName,
            engineTabId: tabId
        });

        // Update access order
        this.updateTabAccess(tabId);

        // DISABLED: Deep sleep management after creating new tab to prevent reloads
        // await this.manageTabSleep();

        console.log(`✅ Tab ${tabId} created with ${engineName} engine`);
        return result;
    }

    async closeTab(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        if (!engine) {
            throw new Error('Engine not found: ' + tabInfo.engine);
        }

        await engine.closeTab(tabInfo.engineTabId);
        this.tabs.delete(tabId);
        
        // Clean up from access order and sleeping tabs
        const accessIndex = this.tabAccessOrder.indexOf(tabId);
        if (accessIndex > -1) {
            this.tabAccessOrder.splice(accessIndex, 1);
        }
        this.sleepingTabs.delete(tabId);
        
        console.log(`✅ Tab ${tabId} closed`);
        return { success: true };
    }

    async showTab(tabId) {
        // DISABLED: Deep sleep check to prevent reloads
        // if (this.isTabSleeping(tabId)) {
        //     console.log(`🌅 Waking sleeping tab ${tabId}...`);
        //     const wakeResult = await this.wakeTabFromSleep(tabId);
        //     if (!wakeResult.success) {
        //         throw new Error('Failed to wake sleeping tab: ' + wakeResult.error);
        //     }
        // }

        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        // Update access order (LRU)
        this.updateTabAccess(tabId);

        // Only hide other tabs that are currently visible, don't freeze background tabs
        for (const [otherTabId, otherTabInfo] of this.tabs.entries()) {
            if (otherTabId !== tabId) {
                const engine = this.engines.get(otherTabInfo.engine);
                const engineTab = engine.tabs.get(otherTabInfo.engineTabId);
                // Only hide if it's currently visible (not frozen)
                if (engineTab && engineTab.visible) {
                    await engine.hideTab(otherTabInfo.engineTabId);
                }
            }
        }

        // Show requested tab and ensure it's properly maintained
        const engine = this.engines.get(tabInfo.engine);
        await engine.showTab(tabInfo.engineTabId);
        
        // CRITICAL: Keep background tabs alive by refreshing their state
        this.maintainBackgroundTabs();
        
        // DISABLED: Deep sleep system completely to prevent reloads
        // if (Math.random() < 0.001) { // 0.1% chance to check deep sleep (almost never)
        //     await this.manageTabSleep();
        // }
        
        return { success: true };
    }

    // Keep background tabs alive to prevent freezing
    maintainBackgroundTabs() {
        for (const [tabId, tabInfo] of this.tabs.entries()) {
            const engine = this.engines.get(tabInfo.engine);
            const engineTab = engine.tabs.get(tabInfo.engineTabId);
            
            if (engineTab && !engineTab.visible) {
                // Keep background tab alive by ensuring it's properly maintained
                try {
                    // Just check if the tab is still responsive
                    engineTab.webContents.getTitle().catch(() => {
                        // If tab is frozen, recreate it
                        console.log(`🔄 Tab ${tabId} appears frozen, refreshing...`);
                        this.refreshFrozenTab(tabId, tabInfo);
                    });
                } catch (error) {
                    console.log(`🔄 Tab ${tabId} is frozen, refreshing...`);
                    this.refreshFrozenTab(tabId, tabInfo);
                }
            }
        }
        
        // Update performance stats
        this.updatePerformanceStats();
        
        // Check if we need aggressive cleanup
        this.checkResourcePressure();
    }
    
    // Update performance statistics
    updatePerformanceStats() {
        const now = Date.now();
        if (now - this.performanceStats.lastUpdate < 5000) return; // Update every 5 seconds
        
        this.performanceStats.activeTabs = this.tabs.size;
        this.performanceStats.sleepingTabs = this.sleepingTabs.size;
        this.performanceStats.lastUpdate = now;
        
        // Get memory usage from main process
        const memUsage = process.memoryUsage();
        this.performanceStats.usedMemory = memUsage.heapUsed;
        this.performanceStats.totalMemory = memUsage.heapTotal;
        
        console.log(`📊 Performance: ${this.performanceStats.activeTabs} active, ${this.performanceStats.sleepingTabs} sleeping, ${Math.round(this.performanceStats.usedMemory / 1024 / 1024)}MB memory`);
    }
    
    // Check resource pressure and optimize accordingly
    checkResourcePressure() {
        const memUsage = process.memoryUsage();
        const memoryPressure = memUsage.heapUsed / memUsage.heapTotal;
        
        // If memory usage is high, be more aggressive with deep sleep
        if (memoryPressure > 0.8) {
            console.log('🚨 High memory pressure detected, triggering aggressive cleanup');
            this.resourceMonitor.performanceMode = true;
            this.maxActiveTabs = Math.max(5, this.maxActiveTabs - 2); // Reduce active tabs
            this.manageTabSleep(); // Force deep sleep
        } else if (memoryPressure < 0.5) {
            // If memory usage is low, allow more tabs
            this.resourceMonitor.performanceMode = false;
            this.maxActiveTabs = Math.min(20, this.maxActiveTabs + 1); // Allow more tabs
        }
        
        // Check for heavy sites and optimize them
        this.optimizeHeavySites();
    }
    
    // Optimize heavy sites like YouTube for better performance
    optimizeHeavySites() {
        for (const [tabId, tabInfo] of this.tabs.entries()) {
            try {
                const engine = this.engines.get(tabInfo.engine);
                if (engine && engine.tabs.has(tabInfo.engineTabId)) {
                    const engineTab = engine.tabs.get(tabInfo.engineTabId);
                    if (engineTab && engineTab.webContents && engineTab.url) {
                        const url = engineTab.url.toLowerCase();
                        
                        // Check if this is a heavy site
                        const isHeavySite = Array.from(this.resourceMonitor.heavySites).some(site => 
                            url.includes(site)
                        );
                        
                        if (isHeavySite && !this.sleepingTabs.has(tabId)) {
                            // Apply heavy site optimizations
                            this.optimizeHeavySiteTab(tabId, engineTab);
                        }
                    }
                }
            } catch (error) {
                console.error(`Error optimizing heavy site tab ${tabId}:`, error);
            }
        }
    }
    
    // Apply specific optimizations for heavy sites
    optimizeHeavySiteTab(tabId, engineTab) {
        try {
            // Only apply safe optimizations that don't interfere with site functionality
            engineTab.webContents.executeJavaScript(`
                // Safe performance optimizations only
                if (window.location.hostname.includes('youtube.com')) {
                    console.log('🎬 YouTube detected - applying safe optimizations');
                    // Only add smooth scrolling, no video manipulation
                    const style = document.createElement('style');
                    style.textContent = \`
                        html, body {
                            scroll-behavior: smooth;
                        }
                    \`;
                    document.head.appendChild(style);
                }
            `).catch(err => console.error('Failed to optimize heavy site:', err));
            
            console.log(`🚀 Applied safe heavy site optimizations to tab ${tabId}`);
        } catch (error) {
            console.error(`Error applying heavy site optimizations to tab ${tabId}:`, error);
        }
    }

    async refreshFrozenTab(tabId, tabInfo) {
        try {
            const engine = this.engines.get(tabInfo.engine);
            const oldTab = engine.tabs.get(tabInfo.engineTabId);
            
            if (oldTab) {
                // Store the current URL before recreating
                const currentUrl = oldTab.url || '';
                
                // Close the frozen tab
                await engine.closeTab(tabInfo.engineTabId);
                
                // Recreate the tab with the same URL
                const result = await engine.createTab(tabInfo.engineTabId, {});
                
                // Navigate to the same URL if it exists
                if (currentUrl) {
                    await engine.navigate(tabInfo.engineTabId, currentUrl);
                }
                
                console.log(`✅ Tab ${tabId} refreshed successfully`);
            }
        } catch (error) {
            console.error(`Failed to refresh frozen tab ${tabId}:`, error);
        }
    }

    // Deep sleep management - put old tabs to sleep when we have too many
    async manageTabSleep() {
        const activeTabs = Array.from(this.tabs.keys());
        
        // Only count web tabs (not AI or extensions tabs) for deep sleep
        const webTabs = activeTabs.filter(tabId => {
            // Check if it's a web tab by seeing if it has an engine tab
            const tabInfo = this.tabs.get(tabId);
            if (!tabInfo) return false;
            
            const engine = this.engines.get(tabInfo.engine);
            if (!engine) return false;
            
            const engineTab = engine.tabs.get(tabInfo.engineTabId);
            return engineTab && engineTab.url && !engineTab.url.startsWith('prism://');
        });
        
        console.log(`🔍 Deep sleep check: ${webTabs.length} web tabs, max: ${this.maxActiveTabs}, access order:`, this.tabAccessOrder);
        
        // If we have more than maxActiveTabs WEB TABS, put oldest to sleep
        if (webTabs.length > this.maxActiveTabs) {
            const tabsToSleep = webTabs.length - this.maxActiveTabs;
            
            // Get the oldest tabs (first in access order) that are actually web tabs
            const oldestTabs = [];
            for (const tabId of this.tabAccessOrder) {
                if (webTabs.includes(tabId) && !this.sleepingTabs.has(tabId)) {
                    oldestTabs.push(tabId);
                    if (oldestTabs.length >= tabsToSleep) break;
                }
            }
            
            console.log(`😴 Putting ${oldestTabs.length} oldest tabs to sleep:`, oldestTabs);
            
            for (const tabId of oldestTabs) {
                await this.putTabToSleep(tabId);
            }
        }
    }

    // Put a tab into deep sleep (freeze it but keep its state)
    async putTabToSleep(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) return;

        try {
            const engine = this.engines.get(tabInfo.engine);
            const engineTab = engine.tabs.get(tabInfo.engineTabId);
            
            if (engineTab) {
                // Store tab state before sleeping
                this.sleepingTabs.set(tabId, {
                    url: engineTab.url || '',
                    title: engineTab.title || 'Sleeping Tab',
                    engine: tabInfo.engine,
                    timestamp: Date.now()
                });
                
                // Hide the tab
                await engine.hideTab(tabInfo.engineTabId);
                
                // Close the BrowserView to free memory
                await engine.closeTab(tabInfo.engineTabId);
                
                // Remove from active tabs but keep in our tracking
                this.tabs.delete(tabId);
                
                console.log(`😴 Tab ${tabId} put to deep sleep`);
            }
        } catch (error) {
            console.error(`Failed to put tab ${tabId} to sleep:`, error);
        }
    }

    // Wake a tab from deep sleep (refresh it)
    async wakeTabFromSleep(tabId) {
        const sleepingTab = this.sleepingTabs.get(tabId);
        if (!sleepingTab) return;

        try {
            // Recreate the tab
            const engine = this.engines.get(sleepingTab.engine);
            const result = await engine.createTab(tabId, {});
            
            // Restore tab info
            this.tabs.set(tabId, {
                engine: sleepingTab.engine,
                engineTabId: tabId
            });
            
            // Navigate to the stored URL
            if (sleepingTab.url) {
                await engine.navigate(tabId, sleepingTab.url);
            }
            
            // Remove from sleeping tabs
            this.sleepingTabs.delete(tabId);
            
            // Update access order
            this.updateTabAccess(tabId);
            
            console.log(`🌅 Tab ${tabId} woken from deep sleep`);
            return { success: true, url: sleepingTab.url, title: sleepingTab.title };
        } catch (error) {
            console.error(`Failed to wake tab ${tabId} from sleep:`, error);
            return { success: false, error: error.message };
        }
    }

    // Update tab access order (LRU) - only for web tabs
    updateTabAccess(tabId) {
        // Only track web tabs in LRU order
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) return;
        
        const engine = this.engines.get(tabInfo.engine);
        if (!engine) return;
        
        const engineTab = engine.tabs.get(tabInfo.engineTabId);
        if (!engineTab || !engineTab.url || engineTab.url.startsWith('prism://')) {
            return; // Skip AI and extensions tabs
        }
        
        // Remove from current position
        const index = this.tabAccessOrder.indexOf(tabId);
        if (index > -1) {
            this.tabAccessOrder.splice(index, 1);
        }
        
        // Add to end (most recently used)
        this.tabAccessOrder.push(tabId);
        
        console.log(`📝 Updated access order for tab ${tabId}, new order:`, this.tabAccessOrder);
    }

    // Check if tab is sleeping
    isTabSleeping(tabId) {
        return this.sleepingTabs.has(tabId);
    }

    // Get sleeping tab info
    getSleepingTabInfo(tabId) {
        return this.sleepingTabs.get(tabId);
    }

    async hideTab(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.hideTab(tabInfo.engineTabId);
        
        return { success: true };
    }

    async navigate(tabId, url) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.navigate(tabInfo.engineTabId, url);
        
        return { success: true };
    }

    async goBack(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.goBack(tabInfo.engineTabId);
        
        return { success: true };
    }

    async goForward(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.goForward(tabInfo.engineTabId);
        
        return { success: true };
    }

    async reload(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.reload(tabInfo.engineTabId);
        
        return { success: true };
    }

    async stop(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        await engine.stop(tabInfo.engineTabId);
        
        return { success: true };
    }

    async getTitle(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        return await engine.getTitle(tabInfo.engineTabId);
    }

    async getURL(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const engine = this.engines.get(tabInfo.engine);
        return await engine.getURL(tabInfo.engineTabId);
    }

    async canGoBack(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            return false;
        }

        const engine = this.engines.get(tabInfo.engine);
        return await engine.canGoBack(tabInfo.engineTabId);
    }

    async canGoForward(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            return false;
        }

        const engine = this.engines.get(tabInfo.engine);
        return await engine.canGoForward(tabInfo.engineTabId);
    }

    async isLoading(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            return false;
        }

        const engine = this.engines.get(tabInfo.engine);
        return await engine.isLoading(tabInfo.engineTabId);
    }

    async switchTabEngine(tabId, newEngineName) {
        console.log(`\n🔄 ========== ENGINE SWITCH START ==========`);
        console.log(`   Tab ID: ${tabId}`);
        console.log(`   Target Engine: ${newEngineName}`);
        
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            console.error(`❌ Tab not found: ${tabId}`);
            console.log(`   Available tabs:`, Array.from(this.tabs.keys()));
            return { success: false, message: 'Tab not found' };
        }

        const oldEngineName = tabInfo.engine;
        console.log(`   Current Engine: ${oldEngineName}`);
        
        if (oldEngineName === newEngineName) {
            console.log(`ℹ️ Tab ${tabId} already using ${newEngineName}`);
            return { success: true, message: 'Already using ' + newEngineName };
        }

        const oldEngine = this.engines.get(oldEngineName);
        const newEngine = this.engines.get(newEngineName);
        
        console.log(`   Old Engine Ready: ${oldEngine?.ready || false}`);
        console.log(`   New Engine Ready: ${newEngine?.ready || false}`);
        
        if (!oldEngine) {
            console.error(`❌ Old engine not found: ${oldEngineName}`);
            console.log(`   Available engines:`, Array.from(this.engines.keys()));
            return { success: false, message: 'Old engine not found' };
        }
        
        if (!newEngine) {
            console.error(`❌ New engine not found: ${newEngineName}`);
            console.log(`   Available engines:`, Array.from(this.engines.keys()));
            return { success: false, message: 'New engine not found' };
        }
        
        if (!newEngine.ready) {
            console.error(`❌ Target engine not ready: ${newEngineName}`);
            return { success: false, message: `${newEngineName} engine not ready` };
        }

        console.log(`🔄 Switching tab ${tabId} from ${oldEngineName} to ${newEngineName}...`);

        let currentUrl = '';
        try {
            // Get current state before closing
            currentUrl = await oldEngine.getURL(tabInfo.engineTabId);
            const oldTab = oldEngine.tabs.get(tabInfo.engineTabId);
            const wasVisible = oldTab ? oldTab.visible : false;

            console.log(`  Current URL: ${currentUrl}, Was visible: ${wasVisible}`);

            // Close tab in old engine
            await oldEngine.closeTab(tabInfo.engineTabId);
            console.log(`  ✓ Closed tab in ${oldEngineName}`);

            // Create tab in new engine
            await newEngine.createTab(tabId, {});
            console.log(`  ✓ Created tab in ${newEngineName}`);
            
            // Navigate to same URL if there was one
            if (currentUrl && currentUrl !== 'about:blank' && currentUrl !== '') {
                await newEngine.navigate(tabId, currentUrl);
                console.log(`  ✓ Navigated to ${currentUrl}`);
            }

            // Show if was visible
            if (wasVisible) {
                await newEngine.showTab(tabId);
                console.log(`  ✓ Tab shown`);
            }

            // Update tab info
            tabInfo.engine = newEngineName;
            tabInfo.engineTabId = tabId;

            console.log(`✅ Tab ${tabId} successfully switched from ${oldEngineName} to ${newEngineName}`);
            console.log(`========== ENGINE SWITCH END ==========\n`);
            return { success: true, oldEngine: oldEngineName, newEngine: newEngineName };
        } catch (error) {
            console.error(`❌ Failed to switch tab ${tabId} from ${oldEngineName} to ${newEngineName}:`);
            console.error(`   Error:`, error.message);
            console.error(`   Stack:`, error.stack);
            console.log(`========== ENGINE SWITCH FAILED ==========\n`);
            
            // Try to recover - recreate tab in old engine
            try {
                console.log(`🔄 Attempting to recover tab in ${oldEngineName}...`);
                await newEngine.closeTab(tabId).catch(() => {});
                await oldEngine.createTab(tabId, {});
                if (currentUrl && currentUrl !== 'about:blank') {
                    await oldEngine.navigate(tabId, currentUrl);
                }
                await oldEngine.showTab(tabId);
                tabInfo.engine = oldEngineName;
                console.log(`✅ Tab recovered in ${oldEngineName}`);
            } catch (recoverError) {
                console.error(`❌ Recovery failed:`, recoverError.message);
            }
            
            return { success: false, message: error.message, oldEngine: oldEngineName };
        }
    }

    handleEngineEvent(event, data) {
        // Forward engine events to renderer process
        if (this.mainWindow && !this.mainWindow.isDestroyed()) {
            this.mainWindow.webContents.send('engine-event', { event, data });
        }

        // Emit to local listeners
        const listeners = this.eventListeners.get(event) || [];
        for (const listener of listeners) {
            try {
                listener(data);
            } catch (error) {
                console.error('Event listener error:', error);
            }
        }
    }

    addEventListener(event, listener) {
        if (!this.eventListeners.has(event)) {
            this.eventListeners.set(event, []);
        }
        this.eventListeners.get(event).push(listener);
    }

    removeEventListener(event, listener) {
        const listeners = this.eventListeners.get(event);
        if (listeners) {
            const index = listeners.indexOf(listener);
            if (index > -1) {
                listeners.splice(index, 1);
            }
        }
    }

    getStats() {
        const stats = {
            totalTabs: this.tabs.size,
            engines: {}
        };

        for (const [name, engine] of this.engines.entries()) {
            stats.engines[name] = engine.getStats();
        }

        return stats;
    }

    async hideAllViews() {
        // Hide all BrowserViews but keep them in memory (for modals/overlays)
        // Don't remove them completely as it breaks the UI
        for (const [tabId, tabInfo] of this.tabs.entries()) {
            const engine = this.engines.get(tabInfo.engine);
            const engineTab = engine.tabs.get(tabInfo.engineTabId);
            if (engineTab && engineTab.visible) {
                await engine.hideTab(tabInfo.engineTabId);
            }
        }
        return { success: true };
    }

    async hideOtherViews(currentTabId) {
        // Hide all OTHER BrowserViews except the current one to prevent content bleeding
        // This prevents the current tab from refreshing when switching
        for (const [tabId, tabInfo] of this.tabs.entries()) {
            if (tabId !== currentTabId) {
                const engine = this.engines.get(tabInfo.engine);
                const engineTab = engine.tabs.get(tabInfo.engineTabId);
                if (engineTab && engineTab.visible) {
                    await engine.hideTab(tabInfo.engineTabId);
                }
            }
        }
        return { success: true };
    }

    async showActiveView() {
        // Find the currently active tab and show its view
        for (const [tabId, tabInfo] of this.tabs.entries()) {
            const engine = this.engines.get(tabInfo.engine);
            const engineTab = engine.tabs.get(tabInfo.engineTabId);
            
            if (engineTab && engineTab.visible) {
                // This tab should be visible, show it
                await engine.showTab(tabInfo.engineTabId);
                return { success: true, tabId };
            }
        }
        
        return { success: true, tabId: null };
    }

    async shutdown() {
        console.log('🛑 Shutting down Engine Manager...');

        // Shutdown all engines
        for (const engine of this.engines.values()) {
            await engine.shutdown();
        }

        this.engines.clear();
        this.tabs.clear();
        this.eventListeners.clear();
        
        console.log('✅ Engine Manager shutdown complete');
    }
}

module.exports = EngineManager;

