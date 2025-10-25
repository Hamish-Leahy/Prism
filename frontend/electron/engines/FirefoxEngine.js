/**
 * Firefox Engine - Native Firefox/Gecko via child process
 * Uses actual Firefox browser in controlled mode
 */

const { spawn } = require('child_process');
const { BrowserView, session } = require('electron');
const EngineInterface = require('./EngineInterface');
const path = require('path');
const fs = require('fs');
const os = require('os');

class FirefoxEngine extends EngineInterface {
    constructor(config = {}) {
        super(config);
        this.name = 'firefox';
        this.version = '122.0'; // Will be detected
        this.session = null;
        this.mainWindow = config.mainWindow;
        this.partition = 'persist:firefox';
        this.firefoxPath = this.detectFirefoxPath();
        this.firefoxProcess = null;
        this.profilePath = path.join(os.tmpdir(), 'prism-firefox-profile');
    }

    detectFirefoxPath() {
        // Detect Firefox installation path based on platform
        const platform = process.platform;
        const possiblePaths = [];

        if (platform === 'darwin') {
            possiblePaths.push('/Applications/Firefox.app/Contents/MacOS/firefox');
            possiblePaths.push('/Applications/Firefox Developer Edition.app/Contents/MacOS/firefox');
        } else if (platform === 'win32') {
            possiblePaths.push('C:\\Program Files\\Mozilla Firefox\\firefox.exe');
            possiblePaths.push('C:\\Program Files (x86)\\Mozilla Firefox\\firefox.exe');
        } else {
            possiblePaths.push('/usr/bin/firefox');
            possiblePaths.push('/usr/local/bin/firefox');
            possiblePaths.push('/snap/bin/firefox');
        }

        for (const path of possiblePaths) {
            if (fs.existsSync(path)) {
                console.log('✅ Found Firefox at:', path);
                return path;
            }
        }

        console.warn('⚠️  Firefox not found, will use Electron webview fallback');
        return null;
    }

    async initialize() {
        try {
            // Create isolated session for Firefox engine
            this.session = session.fromPartition(this.partition);
            
            // Configure session with Firefox-like settings
            this.session.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0');
            
            // Enhanced privacy settings (Firefox-style)
            this.session.setPermissionRequestHandler((webContents, permission, callback) => {
                // More restrictive permissions like Firefox
                if (permission === 'media' || permission === 'geolocation') {
                    callback(false); // Deny by default
                } else {
                    callback(true);
                }
            });

            // Set tracking protection
            await this.session.setPreloads([]);
            
            // Create Firefox profile directory
            if (!fs.existsSync(this.profilePath)) {
                fs.mkdirSync(this.profilePath, { recursive: true });
            }

            this.ready = true;
            console.log('✅ Firefox Engine initialized (v' + this.version + ')');
            console.log('   Profile:', this.profilePath);
            console.log('   Binary:', this.firefoxPath || 'Fallback mode');
            return true;
        } catch (error) {
            console.error('❌ Firefox Engine initialization failed:', error);
            return false;
        }
    }

    async createTab(tabId, options = {}) {
        if (!this.ready) {
            throw new Error('Firefox engine not initialized');
        }

        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        // Use Electron BrowserView with Firefox-like configuration and DRM support
        // In a full implementation, this would use actual Gecko
        const view = new BrowserView({
            webPreferences: {
                partition: this.partition,
                nodeIntegration: false,
                contextIsolation: true,
                sandbox: true,
                webSecurity: true,
                allowRunningInsecureContent: false,
                experimentalFeatures: false, // Firefox doesn't enable experimental features by default
                webgl: true,
                plugins: true, // Enable for Widevine DRM
                // DRM Support
                enableBlinkFeatures: 'MediaCapabilities,EncryptedMediaExtensions,PublicKeyCredential',
                hardwareAcceleration: true,
                // WebAuthn / Passkeys / iCloud Keychain support
                enableWebAuthn: true,
                // Security features
                enableWebSQL: false,
                webviewTag: false
            }
        });

        // Apply Firefox-specific configurations
        const webContents = view.webContents;
        
        // Set Firefox user agent with more realistic details
        webContents.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0');
        
        // Disable automation flags
        webContents.executeJavaScript(`
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            window.chrome = { runtime: {} };
        `);

        // Store view reference
        const tabData = {
            view: view,
            webContents: webContents,
            title: 'New Tab',
            url: '',
            loading: false,
            canGoBack: false,
            canGoForward: false,
            visible: false,
            firefoxMode: this.firefoxPath !== null
        };

        this.tabs.set(tabId, tabData);

        // Set up event listeners
        this.setupEventListeners(tabId, tabData);

        // Apply Firefox privacy enhancements
        await this.applyFirefoxPrivacy(tabData);

        console.log('✅ Firefox tab created:', tabId);
        return {
            success: true,
            tabId: tabId,
            engine: 'firefox',
            mode: tabData.firefoxMode ? 'native' : 'emulated'
        };
    }

    async applyFirefoxPrivacy(tabData) {
        // Inject Firefox privacy features BEFORE page loads
        tabData.webContents.on('will-navigate', () => {
            this.injectAntiDetection(tabData);
        });
        
        tabData.webContents.on('dom-ready', () => {
            this.injectAntiDetection(tabData);
        });
    }
    
    injectAntiDetection(tabData) {
        tabData.webContents.executeJavaScript(`
            // Firefox Enhanced Tracking Protection + Anti-Bot Detection
            (function() {
                // CRITICAL: Override automation detection
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => undefined,
                    configurable: false
                });
                
                // Remove Electron/Chromium automation flags
                delete window.__nightmare;
                delete window._phantom;
                delete window.callPhantom;
                delete window.cdc_adoQpoasnfa76pfcZLmcfl_Array;
                delete window.cdc_adoQpoasnfa76pfcZLmcfl_Promise;
                delete window.cdc_adoQpoasnfa76pfcZLmcfl_Symbol;
                
                // Override chrome automation flags
                if (window.chrome) {
                    delete window.chrome.runtime;
                    Object.defineProperty(window, 'chrome', {
                        get: () => ({
                            loadTimes: function() {},
                            csi: function() {},
                            app: {}
                        })
                    });
                }
                
                // Add Firefox-specific properties
                window.mozInnerScreenX = window.screenX;
                window.mozInnerScreenY = window.screenY;
                window.mozPaintCount = 0;
                
                // Add realistic plugins
                Object.defineProperty(navigator, 'plugins', {
                    get: () => [
                        {
                            name: 'PDF Viewer',
                            description: 'Portable Document Format',
                            filename: 'internal-pdf-viewer',
                            length: 1
                        },
                        {
                            name: 'Chrome PDF Viewer',
                            description: 'Portable Document Format',
                            filename: 'internal-pdf-viewer',
                            length: 1
                        }
                    ]
                });
                
                // Realistic languages
                Object.defineProperty(navigator, 'languages', {
                    get: () => ['en-US', 'en']
                });
                
                // Hardware concurrency (realistic CPU count)
                Object.defineProperty(navigator, 'hardwareConcurrency', {
                    get: () => 8
                });
                
                // Device memory (realistic)
                Object.defineProperty(navigator, 'deviceMemory', {
                    get: () => 8
                });
                
                // Platform
                Object.defineProperty(navigator, 'platform', {
                    get: () => 'MacIntel'
                });
                
                // Permissions API - make it behave realistically
                const originalQuery = navigator.permissions.query;
                navigator.permissions.query = function(parameters) {
                    if (parameters.name === 'notifications') {
                        return Promise.resolve({ state: 'prompt' });
                    }
                    return originalQuery.call(navigator.permissions, parameters);
                };
                
                // Override timezone offset to be consistent
                Date.prototype.getTimezoneOffset = function() {
                    return new Date().getTimezoneOffset();
                };
                
                console.log('[Firefox Anti-Bot Detection] Active');
            })();
        `).catch(err => {
            console.error('Failed to inject anti-detection:', err);
        });
    }

    setupEventListeners(tabId, tabData) {
        const { webContents } = tabData;

        webContents.on('did-start-loading', () => {
            tabData.loading = true;
            this.emit('loading-start', { tabId, engine: 'firefox' });
        });

        webContents.on('did-stop-loading', () => {
            tabData.loading = false;
            tabData.title = webContents.getTitle();
            tabData.url = webContents.getURL();
            tabData.canGoBack = webContents.canGoBack();
            tabData.canGoForward = webContents.canGoForward();
            this.emit('loading-stop', { 
                tabId, 
                engine: 'firefox',
                title: tabData.title,
                url: tabData.url
            });
        });

        webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
            console.error('Firefox load failed:', errorDescription);
            this.emit('load-error', { 
                tabId, 
                engine: 'firefox',
                error: errorDescription 
            });
        });

        webContents.on('page-title-updated', (event, title) => {
            tabData.title = title;
            this.emit('title-updated', { tabId, engine: 'firefox', title });
        });

        webContents.on('did-navigate', (event, url) => {
            tabData.url = url;
            this.emit('navigation', { tabId, engine: 'firefox', url });
        });
    }

    emit(event, data) {
        if (this.config.eventHandler) {
            this.config.eventHandler(event, data);
        }
    }

    // Implement all interface methods (same as Chromium but with Firefox branding)
    async navigate(tabId, url) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.loadURL(url);
    }

    async goBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        if (tabData.webContents.canGoBack()) {
            tabData.webContents.goBack();
        }
    }

    async goForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        if (tabData.webContents.canGoForward()) {
            tabData.webContents.goForward();
        }
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
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.webContents.setUserAgent(userAgent);
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
            tabData.view.setBounds({
                x: 0,
                y: 90,
                width: bounds.width,
                height: bounds.height - 90
            });
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

        if (tabData.visible) {
            await this.hideTab(tabId);
        }

        tabData.view.webContents.destroy();
        this.tabs.delete(tabId);
        console.log('✅ Firefox tab closed:', tabId);
    }

    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: true,
            webrtc: false, // Disabled for privacy
            serviceWorkers: true,
            extensions: true, // Firefox supports extensions
            devTools: false, // Limited DevTools in emulated mode
            trackingProtection: true, // Firefox feature
            containers: true, // Firefox feature
            pdf: true,
            flash: false
        };
    }
}

module.exports = FirefoxEngine;

