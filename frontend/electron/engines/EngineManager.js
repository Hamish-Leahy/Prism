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
        
        // Register IPC handlers immediately so they're available before initialization completes
        this.setupIPC();
    }

    async initialize() {
        try {
            console.log('🚀 Initializing Engine Manager...');

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
                eventHandler: (event, data) => this.handleEngineEvent(event, data),
                extensionManager: this.extensionManager
            });
            
            const prism = new PrismEngine({ 
                mainWindow: this.mainWindow,
                backendUrl: 'http://localhost:8000',
                eventHandler: (event, data) => this.handleEngineEvent(event, data)
            });

            // Initialize engines
            await Promise.all([
                chromium.initialize(),
                firefox.initialize(),
                tor.initialize(),
                prism.initialize()
            ]);

            // Store engines
            this.engines.set('chromium', chromium);
            this.engines.set('firefox', firefox);
            this.engines.set('tor', tor);
            this.engines.set('prism', prism);

            this.initialized = true;
            console.log('✅ Engine Manager initialized');
            console.log('   Available engines:', Array.from(this.engines.keys()).join(', '));
            
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
        
        console.log(`✅ Tab ${tabId} closed`);
        return { success: true };
    }

    async showTab(tabId) {
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        // Hide all other tabs first
        for (const [otherTabId, otherTabInfo] of this.tabs.entries()) {
            if (otherTabId !== tabId) {
                const engine = this.engines.get(otherTabInfo.engine);
                await engine.hideTab(otherTabInfo.engineTabId);
            }
        }

        // Show requested tab
        const engine = this.engines.get(tabInfo.engine);
        await engine.showTab(tabInfo.engineTabId);
        
        return { success: true };
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
        const tabInfo = this.tabs.get(tabId);
        if (!tabInfo) {
            throw new Error('Tab not found: ' + tabId);
        }

        const oldEngineName = tabInfo.engine;
        
        if (oldEngineName === newEngineName) {
            console.log(`Tab ${tabId} already using ${newEngineName}`);
            return { success: true, message: 'Already using ' + newEngineName };
        }

        const oldEngine = this.engines.get(oldEngineName);
        const newEngine = this.engines.get(newEngineName);
        
        if (!oldEngine) {
            throw new Error('Old engine not found: ' + oldEngineName);
        }
        
        if (!newEngine) {
            throw new Error('New engine not found: ' + newEngineName);
        }

        console.log(`🔄 Switching tab ${tabId} from ${oldEngineName} to ${newEngineName}...`);

        try {
            // Get current state before closing
            const currentUrl = await oldEngine.getURL(tabInfo.engineTabId);
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
            return { success: true, oldEngine: oldEngineName, newEngine: newEngineName };
        } catch (error) {
            console.error(`❌ Failed to switch tab ${tabId} from ${oldEngineName} to ${newEngineName}:`, error);
            // Try to recover by keeping the old engine
            tabInfo.engine = oldEngineName;
            throw error;
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
        // Remove all BrowserViews from the window (for modals/overlays)
        this.mainWindow.setBrowserView(null);
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

