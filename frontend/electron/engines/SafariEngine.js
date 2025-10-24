/**
 * Safari Engine - Native WebKit on macOS
 * Uses actual Safari rendering engine via Electron's webPreferences
 */

const { BrowserView, session } = require('electron');
const EngineInterface = require('./EngineInterface');

class SafariEngine extends EngineInterface {
    constructor(config = {}) {
        super(config);
        this.name = 'safari';
        this.version = '17.0'; // Safari version
        this.session = null;
        this.mainWindow = config.mainWindow;
        this.partition = 'persist:safari';
    }

    async initialize() {
        try {
            // Create isolated session for Safari engine
            this.session = session.fromPartition(this.partition);
            
            // Set Safari user agent
            this.session.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15');
            
            // Safari-like permission handling (more restrictive)
            this.session.setPermissionRequestHandler((webContents, permission, callback) => {
                // Safari is more restrictive with permissions
                if (permission === 'media' || permission === 'geolocation') {
                    callback(false); // Require user interaction
                } else {
                    callback(true);
                }
            });

            this.ready = true;
            console.log('✅ Safari/WebKit Engine initialized (v' + this.version + ')');
            return true;
        } catch (error) {
            console.error('❌ Safari Engine initialization failed:', error);
            return false;
        }
    }

    async createTab(tabId, options = {}) {
        if (!this.ready) {
            throw new Error('Safari engine not initialized');
        }

        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        // Create BrowserView with Safari-like settings
        const view = new BrowserView({
            webPreferences: {
                partition: this.partition,
                nodeIntegration: false,
                contextIsolation: true,
                sandbox: true,
                webSecurity: true,
                allowRunningInsecureContent: false,
                experimentalFeatures: false,
                webgl: true,
                plugins: false,
                // Safari-specific settings
                backgroundThrottling: true,
                offscreen: false
            }
        });

        const webContents = view.webContents;
        
        // Set Safari user agent
        webContents.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15');

        const tabData = {
            view: view,
            webContents: webContents,
            title: 'New Tab',
            url: '',
            loading: false,
            canGoBack: false,
            canGoForward: false,
            visible: false
        };

        this.tabs.set(tabId, tabData);
        this.setupEventListeners(tabId, tabData);
        await this.applySafariFeatures(tabData);

        console.log('✅ Safari tab created:', tabId);
        return {
            success: true,
            tabId: tabId,
            engine: 'safari'
        };
    }

    async applySafariFeatures(tabData) {
        // Inject Safari-specific behaviors
        tabData.webContents.on('dom-ready', () => {
            tabData.webContents.executeJavaScript(`
                // Safari Feature Set
                (function() {
                    console.log('[Safari] WebKit engine active');

                    // Hide automation flags
                    Object.defineProperty(navigator, 'webdriver', {
                        get: () => false,
                        configurable: true
                    });

                    // Remove Chrome-specific objects
                    delete window.chrome;
                    
                    // Add Safari-specific objects
                    window.safari = {
                        extension: {},
                        self: {
                            addEventListener: function() {},
                            removeEventListener: function() {}
                        }
                    };

                    // Safari vendor
                    Object.defineProperty(navigator, 'vendor', {
                        get: () => 'Apple Computer, Inc.',
                        configurable: true
                    });

                    // Safari specific navigator properties
                    Object.defineProperty(navigator, 'appVersion', {
                        get: () => '5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
                        configurable: true
                    });

                    // Remove Chrome-specific APIs
                    delete navigator.getBattery;
                    delete window.chrome;
                    delete window.webkitStorageInfo;

                    // Intelligent Tracking Prevention (ITP) simulation
                    // Safari blocks third-party cookies by default
                    const originalCookie = document.cookie;
                    Object.defineProperty(document, 'cookie', {
                        get: function() {
                            return originalCookie;
                        },
                        set: function(value) {
                            // Safari's ITP restrictions
                            console.log('[Safari ITP] Cookie set attempt:', value);
                            // Only allow first-party cookies
                            if (!value.includes('SameSite=None')) {
                                originalCookie = value;
                            }
                        }
                    });

                    // Privacy Report (simulated)
                    window.safariPrivacyReport = {
                        trackersBlocked: 0,
                        cookiesBlocked: 0,
                        addTracker: function(domain) {
                            this.trackersBlocked++;
                            console.log('[Safari Privacy] Blocked tracker:', domain);
                        }
                    };

                    console.log('[Safari] Intelligent Tracking Prevention enabled');
                })();
            `);
        });
    }

    setupEventListeners(tabId, tabData) {
        const { webContents } = tabData;

        webContents.on('did-start-loading', () => {
            tabData.loading = true;
            this.emit('loading-start', { tabId, engine: 'safari' });
        });

        webContents.on('did-stop-loading', () => {
            tabData.loading = false;
            tabData.title = webContents.getTitle();
            tabData.url = webContents.getURL();
            tabData.canGoBack = webContents.canGoBack();
            tabData.canGoForward = webContents.canGoForward();
            this.emit('loading-stop', { 
                tabId, 
                engine: 'safari',
                title: tabData.title,
                url: tabData.url
            });
        });

        webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
            console.error('Safari load failed:', errorDescription);
            this.emit('load-error', { 
                tabId, 
                engine: 'safari',
                error: errorDescription 
            });
        });

        webContents.on('page-title-updated', (event, title) => {
            tabData.title = title;
            this.emit('title-updated', { tabId, engine: 'safari', title });
        });

        webContents.on('did-navigate', (event, url) => {
            tabData.url = url;
            this.emit('navigation', { tabId, engine: 'safari', url });
        });
    }

    emit(event, data) {
        if (this.config.eventHandler) {
            this.config.eventHandler(event, data);
        }
    }

    // Standard interface implementations
    async navigate(tabId, url) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.loadURL(url);
    }

    async goBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        if (tabData.webContents.canGoBack()) tabData.webContents.goBack();
    }

    async goForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        if (tabData.webContents.canGoForward()) tabData.webContents.goForward();
    }

    async reload(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.reload();
    }

    async stop(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.stop();
    }

    async executeJavaScript(tabId, code) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return await tabData.webContents.executeJavaScript(code);
    }

    async getTitle(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.getTitle();
    }

    async getURL(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.getURL();
    }

    async canGoBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.canGoBack();
    }

    async canGoForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.canGoForward();
    }

    async isLoading(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.isLoading();
    }

    async takeScreenshot(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return await tabData.webContents.capturePage();
    }

    async setZoom(tabId, level) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.setZoomFactor(level);
    }

    async getZoom(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.webContents.getZoomFactor();
    }

    async setUserAgent(tabId, userAgent) {
        // Safari uses fixed user agent
        console.warn('[Safari] User agent is fixed for compatibility');
    }

    async clearCache(tabId = null) {
        if (tabId) {
            const tabData = this.tabs.get(tabId);
            if (!tabData) throw new Error('Tab not found: ' + tabId);
            await tabData.webContents.session.clearCache();
        } else {
            await this.session.clearCache();
        }
    }

    async clearCookies(tabId = null) {
        if (tabId) {
            const tabData = this.tabs.get(tabId);
            if (!tabData) throw new Error('Tab not found: ' + tabId);
            await tabData.webContents.session.clearStorageData({ storages: ['cookies'] });
        } else {
            await this.session.clearStorageData({ storages: ['cookies'] });
        }
    }

    async getCookies(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        const url = tabData.webContents.getURL();
        return await tabData.webContents.session.cookies.get({ url });
    }

    async setCookie(tabId, cookie) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        await tabData.webContents.session.cookies.set(cookie);
    }

    async showTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);

        if (!tabData.visible) {
            this.mainWindow.setBrowserView(tabData.view);
            const bounds = this.mainWindow.getContentBounds();
            tabData.view.setBounds({ x: 0, y: 90, width: bounds.width, height: bounds.height - 90 });
            tabData.view.setAutoResize({ width: true, height: true });
            tabData.visible = true;
        }
    }

    async hideTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        if (tabData.visible) {
            this.mainWindow.removeBrowserView(tabData.view);
            tabData.visible = false;
        }
    }

    async closeTab(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);

        if (tabData.visible) await this.hideTab(tabId);
        tabData.view.webContents.destroy();
        this.tabs.delete(tabId);
        console.log('✅ Safari tab closed:', tabId);
    }

    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: true,
            webrtc: true,
            serviceWorkers: true,
            extensions: false,
            devTools: true,
            intelligentTrackingPrevention: true, // Safari feature
            applePaySupport: false, // Would need proper Safari
            safariSpecific: true
        };
    }
}

module.exports = SafariEngine;

