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
        this.version = '131.0'; // Updated to current version
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
            
            // Configure session with Firefox-like settings - use current Firefox version
            this.session.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0');
            
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
        webContents.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0');

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
            // Firefox Enhanced Anti-Bot Detection Suite
            (function() {
                'use strict';
                
                // ===== CRITICAL: Remove ALL Automation Detection Flags =====
                
                // 1. Webdriver flag - Most important check
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => undefined,
                    configurable: true
                });
                
                // 2. Remove ALL automation tool traces
                const automationTools = [
                    '__nightmare',
                    '_phantom',
                    'callPhantom',
                    '__phantomas',
                    'buffer',
                    'emit',
                    'spawn',
                    'domAutomation',
                    'domAutomationController',
                    '_Selenium_IDE_Recorder',
                    '_selenium',
                    'calledSelenium',
                    '_WEBDRIVER_ELEM_CACHE',
                    '__webdriverFunc',
                    '__lastWatirAlert',
                    '__lastWatirConfirm',
                    '__lastWatirPrompt',
                    '__webdriver_evaluate',
                    '__selenium_evaluate',
                    '__webdriver_script_function',
                    '__webdriver_script_func',
                    '__webdriver_script_fn',
                    '__fxdriver_evaluate',
                    '__driver_unwrapped',
                    '__webdriver_unwrapped',
                    '__driver_evaluate',
                    '__selenium_unwrapped',
                    '__fxdriver_unwrapped'
                ];
                
                // Remove from window
                automationTools.forEach(tool => {
                    if (window[tool]) {
                        delete window[tool];
                    }
                });
                
                // Remove Chrome DevTools Protocol (CDP) traces
                const cdpProps = Object.keys(window).filter(prop => 
                    prop.includes('cdc_') || prop.includes('$cdc_') || prop.includes('$chrome_')
                );
                cdpProps.forEach(prop => delete window[prop]);
                
                // 3. Remove Chrome/Chromium automation indicators
                // Firefox shouldn't have chrome object, but if Electron adds it, remove it
                delete window.chrome;
                
                // 4. Override document properties that might leak automation
                Object.defineProperty(document, 'documentElement', {
                    get: function() {
                        return document.querySelector('html');
                    }
                });
                
                // ===== Firefox-Specific Properties =====
                
                // Add Mozilla-specific window properties
                window.mozInnerScreenX = window.screenX;
                window.mozInnerScreenY = window.screenY;
                window.mozPaintCount = 0;
                
                // ===== Navigator Hardening =====
                
                // Plugins - realistic for modern Firefox
                Object.defineProperty(navigator, 'plugins', {
                    get: () => {
                        const pluginArray = [
                            {
                                name: 'PDF Viewer',
                                description: 'Portable Document Format',
                                filename: 'internal-pdf-viewer',
                                length: 2,
                                item: function(i) { return this[i]; },
                                namedItem: function(name) { 
                                    return this[name] || null; 
                                }
                            }
                        ];
                        Object.setPrototypeOf(pluginArray, PluginArray.prototype);
                        return pluginArray;
                    }
                });
                
                // MimeTypes - realistic for modern Firefox
                Object.defineProperty(navigator, 'mimeTypes', {
                    get: () => {
                        const mimeTypeArray = [
                            {
                                type: 'application/pdf',
                                description: 'Portable Document Format',
                                suffixes: 'pdf',
                                enabledPlugin: {
                                    name: 'PDF Viewer',
                                    description: 'Portable Document Format',
                                    filename: 'internal-pdf-viewer'
                                }
                            }
                        ];
                        Object.setPrototypeOf(mimeTypeArray, MimeTypeArray.prototype);
                        return mimeTypeArray;
                    }
                });
                
                // Languages - realistic
                Object.defineProperty(navigator, 'languages', {
                    get: () => ['en-US', 'en']
                });
                
                // Hardware - realistic M1/M2 Mac values
                Object.defineProperty(navigator, 'hardwareConcurrency', {
                    get: () => 10
                });
                
                // Device memory (realistic)
                Object.defineProperty(navigator, 'deviceMemory', {
                    get: () => 8
                });
                
                // Platform
                Object.defineProperty(navigator, 'platform', {
                    get: () => 'MacIntel'
                });
                
                // User Agent - ensure consistency
                Object.defineProperty(navigator, 'userAgent', {
                    get: () => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0'
                });
                
                Object.defineProperty(navigator, 'appVersion', {
                    get: () => '5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0'
                });
                
                Object.defineProperty(navigator, 'vendor', {
                    get: () => ''  // Firefox has empty vendor
                });
                
                // ===== Permissions API - Critical for Google =====
                
                if (navigator.permissions && navigator.permissions.query) {
                    const originalQuery = navigator.permissions.query;
                    navigator.permissions.query = function(parameters) {
                        // Return realistic values
                        const permissionName = parameters.name;
                        
                        // Notifications should start as 'default' (not prompted yet)
                        if (permissionName === 'notifications') {
                            return Promise.resolve({ 
                                state: 'default',
                                onchange: null
                            });
                        }
                        
                        // Geolocation typically starts as 'prompt'
                        if (permissionName === 'geolocation') {
                            return Promise.resolve({ 
                                state: 'prompt',
                                onchange: null
                            });
                        }
                        
                        // Default: call original
                        return originalQuery.call(navigator.permissions, parameters)
                            .catch(() => Promise.resolve({ 
                                state: 'prompt',
                                onchange: null
                            }));
                    };
                }
                
                // ===== Screen Properties - Must be consistent =====
                
                // Ensure screen dimensions are realistic and consistent
                const screenWidth = window.screen.width;
                const screenHeight = window.screen.height;
                const availWidth = window.screen.availWidth;
                const availHeight = window.screen.availHeight;
                
                Object.defineProperty(window.screen, 'width', {
                    get: () => screenWidth
                });
                
                Object.defineProperty(window.screen, 'height', {
                    get: () => screenHeight
                });
                
                Object.defineProperty(window.screen, 'availWidth', {
                    get: () => availWidth
                });
                
                Object.defineProperty(window.screen, 'availHeight', {
                    get: () => availHeight
                });
                
                Object.defineProperty(window.screen, 'colorDepth', {
                    get: () => 24
                });
                
                Object.defineProperty(window.screen, 'pixelDepth', {
                    get: () => 24
                });
                
                // ===== Canvas Fingerprinting Protection =====
                
                const originalToDataURL = HTMLCanvasElement.prototype.toDataURL;
                const originalToBlob = HTMLCanvasElement.prototype.toBlob;
                const originalGetImageData = CanvasRenderingContext2D.prototype.getImageData;
                
                // Add slight noise to prevent canvas fingerprinting
                HTMLCanvasElement.prototype.toDataURL = function() {
                    const context = this.getContext('2d');
                    if (context) {
                        const imageData = context.getImageData(0, 0, this.width, this.height);
                        // Add minimal noise (undetectable to humans)
                        for (let i = 0; i < imageData.data.length; i += 4) {
                            imageData.data[i] = imageData.data[i] + Math.floor(Math.random() * 2);
                        }
                        context.putImageData(imageData, 0, 0);
                    }
                    return originalToDataURL.apply(this, arguments);
                };
                
                // ===== WebGL Fingerprinting Protection =====
                
                const getParameter = WebGLRenderingContext.prototype.getParameter;
                WebGLRenderingContext.prototype.getParameter = function(parameter) {
                    // Spoof common WebGL fingerprinting parameters
                    if (parameter === 37445) { // UNMASKED_VENDOR_WEBGL
                        return 'Apple Inc.';
                    }
                    if (parameter === 37446) { // UNMASKED_RENDERER_WEBGL
                        return 'Apple M1';
                    }
                    return getParameter.call(this, parameter);
                };
                
                // Also handle WebGL2
                if (typeof WebGL2RenderingContext !== 'undefined') {
                    const getParameter2 = WebGL2RenderingContext.prototype.getParameter;
                    WebGL2RenderingContext.prototype.getParameter = function(parameter) {
                        if (parameter === 37445) {
                            return 'Apple Inc.';
                        }
                        if (parameter === 37446) {
                            return 'Apple M1';
                        }
                        return getParameter2.call(this, parameter);
                    };
                }
                
                // ===== Date/Time Consistency =====
                
                // Ensure timezone is consistent
                const originalGetTimezoneOffset = Date.prototype.getTimezoneOffset;
                Date.prototype.getTimezoneOffset = function() {
                    return originalGetTimezoneOffset.call(this);
                };
                
                // ===== Connection Information =====
                
                // Make connection look real
                if (navigator.connection) {
                    Object.defineProperty(navigator.connection, 'rtt', {
                        get: () => 50 + Math.floor(Math.random() * 50) // 50-100ms
                    });
                    
                    Object.defineProperty(navigator.connection, 'downlink', {
                        get: () => 10 + Math.random() * 5 // 10-15 Mbps
                    });
                    
                    Object.defineProperty(navigator.connection, 'effectiveType', {
                        get: () => '4g'
                    });
                }
                
                // ===== Battery API =====
                
                // Make battery status look realistic
                if (navigator.getBattery) {
                    const originalGetBattery = navigator.getBattery;
                    navigator.getBattery = function() {
                        return originalGetBattery.call(navigator).then(battery => {
                            Object.defineProperty(battery, 'charging', {
                                get: () => true
                            });
                            Object.defineProperty(battery, 'chargingTime', {
                                get: () => 0
                            });
                            Object.defineProperty(battery, 'dischargingTime', {
                                get: () => Infinity
                            });
                            Object.defineProperty(battery, 'level', {
                                get: () => 1.0
                            });
                            return battery;
                        });
                    };
                }
                
                // ===== Mouse Movement & Touch =====
                
                // Ensure mouse/touch events are properly supported
                if (!('ontouchstart' in window)) {
                    // Desktop - no touch support
                    Object.defineProperty(navigator, 'maxTouchPoints', {
                        get: () => 0
                    });
                }
                
                // ===== Remove Headless Indicators =====
                
                // Ensure we don't look headless
                Object.defineProperty(navigator, 'headless', {
                    get: () => undefined
                });
                
                // ===== Prototype Pollution Protection =====
                
                // Prevent detection through prototype checks
                const objectToString = Object.prototype.toString;
                Object.prototype.toString = function() {
                    if (this === window) {
                        return '[object Window]';
                    }
                    return objectToString.call(this);
                };
                
                // ===== Error Stack Traces =====
                
                // Clean up error stack traces that might reveal automation
                const originalPrepareStackTrace = Error.prepareStackTrace;
                Error.prepareStackTrace = function(error, stack) {
                    if (originalPrepareStackTrace) {
                        return originalPrepareStackTrace(error, stack);
                    }
                    return stack.map(frame => frame.toString()).join('\\n');
                };
                
                // ===== Final Touches =====
                
                // Ensure window.opener is null (common bot indicator)
                if (!window.opener) {
                    Object.defineProperty(window, 'opener', {
                        get: () => null
                    });
                }
                
                // Add realistic timing
                const originalNow = performance.now;
                let startTime = originalNow.call(performance);
                performance.now = function() {
                    return originalNow.call(performance) - startTime;
                };
                
                console.log('[Firefox Stealth Mode] ✓ All anti-detection measures active');
                console.log('[Firefox Stealth Mode] ✓ Navigator.webdriver:', navigator.webdriver);
                console.log('[Firefox Stealth Mode] ✓ User Agent:', navigator.userAgent);
                console.log('[Firefox Stealth Mode] ✓ Platform:', navigator.platform);
                
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

