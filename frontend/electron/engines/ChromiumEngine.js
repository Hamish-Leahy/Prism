/**
 * Chromium Engine - Native Chromium via Electron BrowserView
 * Uses Electron's actual Chromium engine for rendering
 */

const { BrowserView, session } = require('electron');
const EngineInterface = require('./EngineInterface');

class ChromiumEngine extends EngineInterface {
    constructor(config = {}) {
        super(config);
        this.name = 'chromium';
        this.version = process.versions.chrome;
        this.session = null;
        this.mainWindow = config.mainWindow;
        this.partition = 'persist:chromium';
    }

    async initialize() {
        try {
            // Create isolated session for Chromium engine
            this.session = session.fromPartition(this.partition);
            
            // Configure session
            this.session.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' + this.version + ' Safari/537.36');
            
            // Set permissions
            this.session.setPermissionRequestHandler((webContents, permission, callback) => {
                // Allow all permissions for Chromium (standard browser behavior)
                callback(true);
            });

            this.ready = true;
            console.log('✅ Chromium Engine initialized (v' + this.version + ')');
            return true;
        } catch (error) {
            console.error('❌ Chromium Engine initialization failed:', error);
            return false;
        }
    }

    async createTab(tabId, options = {}) {
        if (!this.ready) {
            throw new Error('Chromium engine not initialized');
        }

        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        // Create BrowserView (actual Chromium view)
        const view = new BrowserView({
            webPreferences: {
                partition: this.partition,
                nodeIntegration: false,
                contextIsolation: true,
                sandbox: true,
                webSecurity: true,
                allowRunningInsecureContent: false,
                experimentalFeatures: true,
                webgl: true,
                plugins: false
            }
        });

        // Store view reference
        const tabData = {
            view: view,
            webContents: view.webContents,
            title: 'New Tab',
            url: '',
            loading: false,
            canGoBack: false,
            canGoForward: false,
            visible: false
        };

        this.tabs.set(tabId, tabData);

        // Set up event listeners
        this.setupEventListeners(tabId, tabData);

        console.log('✅ Chromium tab created:', tabId);
        return {
            success: true,
            tabId: tabId,
            engine: 'chromium'
        };
    }

    setupEventListeners(tabId, tabData) {
        const { webContents } = tabData;

        webContents.on('did-start-loading', () => {
            tabData.loading = true;
            this.emit('loading-start', { tabId, engine: 'chromium' });
        });

        webContents.on('did-stop-loading', () => {
            tabData.loading = false;
            tabData.title = webContents.getTitle();
            tabData.url = webContents.getURL();
            tabData.canGoBack = webContents.canGoBack();
            tabData.canGoForward = webContents.canGoForward();
            this.emit('loading-stop', { 
                tabId, 
                engine: 'chromium',
                title: tabData.title,
                url: tabData.url
            });
        });

        webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
            console.error('Chromium load failed:', errorDescription);
            this.emit('load-error', { 
                tabId, 
                engine: 'chromium',
                error: errorDescription 
            });
        });

        webContents.on('page-title-updated', (event, title) => {
            tabData.title = title;
            this.emit('title-updated', { tabId, engine: 'chromium', title });
        });

        webContents.on('did-navigate', (event, url) => {
            tabData.url = url;
            this.emit('navigation', { tabId, engine: 'chromium', url });
        });

        webContents.on('did-navigate-in-page', (event, url) => {
            tabData.url = url;
            this.emit('navigation-in-page', { tabId, engine: 'chromium', url });
        });
    }

    emit(event, data) {
        // Emit to main process or event system
        // This will be handled by EngineManager
        if (this.config.eventHandler) {
            this.config.eventHandler(event, data);
        }
    }

    async navigate(tabId, url) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.loadURL(url);
    }

    async goBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        if (tabData.webContents.canGoBack()) {
            tabData.webContents.goBack();
        }
    }

    async goForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        if (tabData.webContents.canGoForward()) {
            tabData.webContents.goForward();
        }
    }

    async reload(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.reload();
    }

    async stop(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.stop();
    }

    async executeJavaScript(tabId, code) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return await tabData.webContents.executeJavaScript(code);
    }

    async getTitle(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.getTitle();
    }

    async getURL(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.getURL();
    }

    async canGoBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.canGoBack();
    }

    async canGoForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.canGoForward();
    }

    async isLoading(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.isLoading();
    }

    async takeScreenshot(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return await tabData.webContents.capturePage();
    }

    async setZoom(tabId, level) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.setZoomFactor(level);
    }

    async getZoom(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        return tabData.webContents.getZoomFactor();
    }

    async setUserAgent(tabId, userAgent) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.setUserAgent(userAgent);
    }

    async clearCache(tabId = null) {
        if (tabId) {
            const tabData = this.tabs.get(tabId);
            if (!tabData) {
                throw new Error('Tab not found: ' + tabId);
            }
            await tabData.webContents.session.clearCache();
        } else {
            await this.session.clearCache();
        }
    }

    async clearCookies(tabId = null) {
        if (tabId) {
            const tabData = this.tabs.get(tabId);
            if (!tabData) {
                throw new Error('Tab not found: ' + tabId);
            }
            await tabData.webContents.session.clearStorageData({ storages: ['cookies'] });
        } else {
            await this.session.clearStorageData({ storages: ['cookies'] });
        }
    }

    async getCookies(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        const url = tabData.webContents.getURL();
        return await tabData.webContents.session.cookies.get({ url });
    }

    async setCookie(tabId, cookie) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        await tabData.webContents.session.cookies.set(cookie);
    }

    async showTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        if (!tabData.visible) {
            this.mainWindow.setBrowserView(tabData.view);
            const bounds = this.mainWindow.getContentBounds();
            tabData.view.setBounds({
                x: 0,
                y: 90, // Below toolbar
                width: bounds.width,
                height: bounds.height - 90
            });
            tabData.view.setAutoResize({
                width: true,
                height: true
            });
            tabData.visible = true;
        }
    }

    async hideTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        if (tabData.visible) {
            this.mainWindow.removeBrowserView(tabData.view);
            tabData.visible = false;
        }
    }

    async closeTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        // Hide if visible
        if (tabData.visible) {
            await this.hideTab(tabId);
        }

        // Destroy the view
        tabData.view.webContents.destroy();
        
        // Remove from tabs
        this.tabs.delete(tabId);
        
        console.log('✅ Chromium tab closed:', tabId);
    }

    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: true,
            webrtc: true,
            serviceWorkers: true,
            extensions: true, // Chromium supports extensions
            devTools: true, // Full DevTools support
            pdf: true,
            flash: false
        };
    }

    async openDevTools(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.openDevTools();
    }

    async closeDevTools(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) {
            throw new Error('Tab not found: ' + tabId);
        }

        tabData.webContents.closeDevTools();
    }
}

module.exports = ChromiumEngine;

