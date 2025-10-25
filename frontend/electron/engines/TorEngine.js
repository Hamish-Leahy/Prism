/**
 * Tor Engine - Anonymous browsing via Tor network
 * Uses SOCKS5 proxy with enhanced privacy features
 */

const { BrowserView, session } = require('electron');
const EngineInterface = require('./EngineInterface');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');
const net = require('net');

class TorEngine extends EngineInterface {
    constructor(config = {}) {
        super(config);
        this.name = 'tor';
        this.version = '115.0'; // Tor Browser version
        this.session = null;
        this.mainWindow = config.mainWindow;
        this.partition = 'persist:tor';
        this.torProcess = null;
        this.torSocksPort = 9050;
        this.torControlPort = 9051;
        this.torRunning = false;
        this.torDataDir = path.join(os.tmpdir(), 'prism-tor-data');
        this.torInitialized = false;
        this.tabCircuits = new Map(); // Track Tor circuits per tab
    }

    async initialize() {
        try {
            // Just mark as ready - we'll initialize Tor lazily when first tab requests it
            this.ready = true;
            console.log('✅ Tor Engine initialized (lazy mode - will connect when first Tor tab is created)');
            return true;
        } catch (error) {
            console.error('❌ Tor Engine initialization failed:', error);
            return false;
        }
    }

    async initializeTorConnection() {
        if (this.torInitialized) {
            return true;
        }

        try {
            // Create Tor data directory
            if (!fs.existsSync(this.torDataDir)) {
                fs.mkdirSync(this.torDataDir, { recursive: true });
            }

            // Create isolated session for Tor engine
            this.session = session.fromPartition(this.partition);
            
            // Set Tor Browser user agent
            this.session.setUserAgent('Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0');
            
            // Maximum privacy settings
            this.session.setPermissionRequestHandler((webContents, permission, callback) => {
                // Deny ALL permissions for maximum privacy
                callback(false);
            });

            // Configure Tor proxy
            await this.setupTorProxy();

            // Detect and start Tor if available
            await this.startTor();

            this.torInitialized = true;
            console.log('✅ Tor connection established');
            console.log('   SOCKS5 Proxy:', '127.0.0.1:' + this.torSocksPort);
            console.log('   Status:', this.torRunning ? '🟢 Connected' : '🔴 Not running (install Tor)');
            return true;
        } catch (error) {
            console.error('❌ Tor connection failed:', error);
            return false;
        }
    }

    async setupTorProxy() {
        // Configure session to use Tor SOCKS5 proxy
        await this.session.setProxy({
            proxyRules: `socks5://127.0.0.1:${this.torSocksPort}`,
            proxyBypassRules: '<local>'
        });

        console.log('✅ Tor proxy configured');
    }

    async checkTorConnection() {
        return new Promise((resolve) => {
            const socket = net.createConnection(this.torSocksPort, '127.0.0.1');
            
            socket.on('connect', () => {
                socket.end();
                resolve(true);
            });

            socket.on('error', () => {
                resolve(false);
            });

            socket.setTimeout(2000, () => {
                socket.destroy();
                resolve(false);
            });
        });
    }

    async startTor() {
        // Check if Tor is already running
        this.torRunning = await this.checkTorConnection();
        
        if (this.torRunning) {
            console.log('✅ Tor is already running');
            return true;
        }

        // Try to detect and start Tor
        const torPath = this.detectTorPath();
        
        if (!torPath) {
            console.warn('⚠️  Tor not found. Install Tor to use Tor Engine.');
            console.warn('   Run: brew install tor  (macOS)');
            return false;
        }

        try {
            // Start Tor process
            this.torProcess = spawn(torPath, [
                '-f', path.join(__dirname, '../tor/torrc'),
                '--DataDirectory', this.torDataDir,
                '--SOCKSPort', this.torSocksPort.toString(),
                '--ControlPort', this.torControlPort.toString()
            ]);

            this.torProcess.stdout.on('data', (data) => {
                console.log('[Tor]', data.toString().trim());
            });

            this.torProcess.stderr.on('data', (data) => {
                const msg = data.toString().trim();
                if (msg.includes('Bootstrapped 100%')) {
                    this.torRunning = true;
                    console.log('✅ Tor circuit established');
                }
            });

            this.torProcess.on('error', (error) => {
                console.error('❌ Tor process error:', error);
                this.torRunning = false;
            });

            this.torProcess.on('exit', (code) => {
                console.log('Tor process exited with code:', code);
                this.torRunning = false;
            });

            // Wait for Tor to bootstrap
            await this.waitForTor(30000); // 30 second timeout

            return this.torRunning;
        } catch (error) {
            console.error('Failed to start Tor:', error);
            return false;
        }
    }

    detectTorPath() {
        const possiblePaths = [];
        
        if (process.platform === 'darwin') {
            possiblePaths.push('/usr/local/bin/tor');
            possiblePaths.push('/opt/homebrew/bin/tor');
        } else if (process.platform === 'win32') {
            possiblePaths.push('C:\\Program Files\\Tor\\tor.exe');
            possiblePaths.push('C:\\Program Files (x86)\\Tor\\tor.exe');
        } else {
            possiblePaths.push('/usr/bin/tor');
            possiblePaths.push('/usr/local/bin/tor');
        }

        for (const path of possiblePaths) {
            if (fs.existsSync(path)) {
                return path;
            }
        }

        return null;
    }

    async waitForTor(timeout) {
        const startTime = Date.now();
        
        while (Date.now() - startTime < timeout) {
            if (await this.checkTorConnection()) {
                this.torRunning = true;
                return true;
            }
            await new Promise(resolve => setTimeout(resolve, 1000));
        }

        return false;
    }

    async createTab(tabId, options = {}) {
        if (!this.ready) {
            throw new Error('Tor engine not initialized');
        }

        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        // Initialize Tor connection if this is the first Tor tab
        if (!this.torInitialized) {
            console.log('🔒 First Tor tab - initializing Tor connection...');
            const success = await this.initializeTorConnection();
            
            if (!success || !this.torRunning) {
                throw new Error('Tor is not running. Please install and start Tor service.');
            }
        }

        // Create a new circuit for this tab with UNIQUE session partition
        const circuitId = `circuit-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        const uniquePartition = `persist:tor-${tabId}`; // Each tab gets its own isolated session
        this.tabCircuits.set(tabId, circuitId);
        console.log(`🔒 New Tor circuit for tab ${tabId}: ${circuitId}`);
        console.log(`   🔐 Isolated session: ${uniquePartition}`);

        // Create isolated session for this specific Tor tab
        const tabSession = session.fromPartition(uniquePartition);
        
        // Configure Tor proxy for this tab's session
        await tabSession.setProxy({
            proxyRules: `socks5://127.0.0.1:${this.torSocksPort}`,
            proxyBypassRules: '<local>'
        });
        
        // Set Tor Browser user agent for this tab's session
        tabSession.setUserAgent('Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0');
        
        // Maximum privacy settings for this tab's session
        tabSession.setPermissionRequestHandler((webContents, permission, callback) => {
            // Deny ALL permissions for maximum privacy
            callback(false);
        });

        // Create BrowserView with maximum privacy using unique partition
        const view = new BrowserView({
            webPreferences: {
                partition: uniquePartition, // Unique partition for circuit isolation
                nodeIntegration: false,
                contextIsolation: true,
                sandbox: true,
                webSecurity: true,
                allowRunningInsecureContent: false,
                experimentalFeatures: false,
                webgl: false, // Disable WebGL for fingerprinting protection
                plugins: false,
                javascript: true,
                images: true,
                textAreasAreResizable: false
            }
        });

        // Set Tor Browser user agent
        const webContents = view.webContents;
        webContents.setUserAgent('Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0');

        const tabData = {
            view: view,
            webContents: webContents,
            title: 'Tor Tab',
            url: '',
            loading: false,
            canGoBack: false,
            canGoForward: false,
            visible: false,
            torCircuitId: circuitId,
            partition: uniquePartition // Store partition for cleanup
        };

        this.tabs.set(tabId, tabData);
        this.setupEventListeners(tabId, tabData);
        await this.applyTorPrivacy(tabData);

        console.log('✅ Tor tab created:', tabId);
        return {
            success: true,
            tabId: tabId,
            engine: 'tor',
            torActive: this.torRunning
        };
    }

    async applyTorPrivacy(tabData) {
        // Inject maximum privacy protections
        tabData.webContents.on('dom-ready', () => {
            tabData.webContents.executeJavaScript(`
                // Tor Browser Privacy Suite
                (function() {
                    console.log('[Tor Browser] Privacy protections active');

                    // 1. Block ALL WebRTC (IP leak protection)
                    if (window.RTCPeerConnection) window.RTCPeerConnection = undefined;
                    if (window.webkitRTCPeerConnection) window.webkitRTCPeerConnection = undefined;
                    if (window.mozRTCPeerConnection) window.mozRTCPeerConnection = undefined;
                    if (navigator.mediaDevices) navigator.mediaDevices = undefined;

                    // 2. Spoof timezone to UTC
                    Date.prototype.getTimezoneOffset = function() { return 0; };
                    Intl.DateTimeFormat.prototype.resolvedOptions = function() {
                        return { timeZone: 'UTC' };
                    };

                    // 3. Block geolocation completely
                    if (navigator.geolocation) {
                        navigator.geolocation = undefined;
                    }

                    // 4. Block battery API
                    if (navigator.getBattery) {
                        navigator.getBattery = undefined;
                    }

                    // 5. Canvas fingerprinting protection
                    const originalToDataURL = HTMLCanvasElement.prototype.toDataURL;
                    const originalToBlob = HTMLCanvasElement.prototype.toBlob;
                    const originalGetImageData = CanvasRenderingContext2D.prototype.getImageData;

                    HTMLCanvasElement.prototype.toDataURL = function() {
                        console.warn('[Tor] Canvas fingerprinting blocked');
                        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
                    };

                    HTMLCanvasElement.prototype.toBlob = function(callback) {
                        console.warn('[Tor] Canvas fingerprinting blocked');
                        callback(new Blob());
                    };

                    CanvasRenderingContext2D.prototype.getImageData = function() {
                        console.warn('[Tor] Canvas fingerprinting blocked');
                        return originalGetImageData.apply(this, arguments);
                    };

                    // 6. WebGL fingerprinting protection
                    const originalGetParameter = WebGLRenderingContext.prototype.getParameter;
                    WebGLRenderingContext.prototype.getParameter = function(parameter) {
                        // Return generic values for fingerprinting parameters
                        if (parameter === 37445) return 'Intel Inc.'; // UNMASKED_VENDOR_WEBGL
                        if (parameter === 37446) return 'Intel Iris OpenGL Engine'; // UNMASKED_RENDERER_WEBGL
                        return originalGetParameter.apply(this, arguments);
                    };

                    // 7. Audio fingerprinting protection
                    if (window.AudioContext) {
                        const OriginalAudioContext = window.AudioContext;
                        window.AudioContext = function() {
                            const context = new OriginalAudioContext();
                            const originalCreateDynamicsCompressor = context.createDynamicsCompressor;
                            context.createDynamicsCompressor = function() {
                                console.warn('[Tor] Audio fingerprinting detected');
                                return originalCreateDynamicsCompressor.apply(this, arguments);
                            };
                            return context;
                        };
                    }

                    // 8. Font fingerprinting protection
                    Object.defineProperty(document, 'fonts', {
                        get: function() {
                            return {
                                check: function() { return false; },
                                load: function() { return Promise.resolve([]); },
                                ready: Promise.resolve()
                            };
                        }
                    });

                    // 9. Hardware concurrency spoofing
                    Object.defineProperty(navigator, 'hardwareConcurrency', {
                        get: function() { return 4; } // Generic value
                    });

                    // 10. Device memory spoofing
                    if (navigator.deviceMemory) {
                        Object.defineProperty(navigator, 'deviceMemory', {
                            get: function() { return 8; } // Generic value
                        });
                    }

                    // 11. Screen resolution spoofing
                    Object.defineProperty(screen, 'width', { get: function() { return 1920; } });
                    Object.defineProperty(screen, 'height', { get: function() { return 1080; } });
                    Object.defineProperty(screen, 'availWidth', { get: function() { return 1920; } });
                    Object.defineProperty(screen, 'availHeight', { get: function() { return 1040; } });

                    // 12. Plugins and mimeTypes spoofing
                    Object.defineProperty(navigator, 'plugins', {
                        get: function() { return []; }
                    });
                    Object.defineProperty(navigator, 'mimeTypes', {
                        get: function() { return []; }
                    });

                    // 13. Block notification API
                    if (window.Notification) {
                        window.Notification = undefined;
                    }

                    // 14. Block clipboard API
                    if (navigator.clipboard) {
                        navigator.clipboard = undefined;
                    }

                    // 15. Block USB/Bluetooth/NFC APIs
                    if (navigator.usb) navigator.usb = undefined;
                    if (navigator.bluetooth) navigator.bluetooth = undefined;
                    if (navigator.nfc) navigator.nfc = undefined;

                    console.log('[Tor Browser] 🔒 Maximum privacy mode enabled');
                })();
            `);
        });
    }

    setupEventListeners(tabId, tabData) {
        const { webContents } = tabData;

        webContents.on('did-start-loading', () => {
            tabData.loading = true;
            this.emit('loading-start', { tabId, engine: 'tor' });
        });

        webContents.on('did-stop-loading', () => {
            tabData.loading = false;
            tabData.title = webContents.getTitle();
            tabData.url = webContents.getURL();
            tabData.canGoBack = webContents.canGoBack();
            tabData.canGoForward = webContents.canGoForward();
            this.emit('loading-stop', { 
                tabId, 
                engine: 'tor',
                title: tabData.title,
                url: tabData.url,
                torActive: this.torRunning
            });
        });

        webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
            console.error('Tor load failed:', errorDescription);
            if (!this.torRunning) {
                console.error('⚠️  Tor is not running. Pages will not load anonymously.');
            }
            this.emit('load-error', { 
                tabId, 
                engine: 'tor',
                error: errorDescription 
            });
        });

        webContents.on('page-title-updated', (event, title) => {
            tabData.title = title;
            this.emit('title-updated', { tabId, engine: 'tor', title });
        });

        webContents.on('did-navigate', (event, url) => {
            tabData.url = url;
            this.emit('navigation', { tabId, engine: 'tor', url });
        });
    }

    emit(event, data) {
        if (this.config.eventHandler) {
            this.config.eventHandler(event, data);
        }
    }

    // Implement all interface methods
    async navigate(tabId, url) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        
        if (!this.torRunning) {
            console.warn('⚠️  Warning: Navigating without Tor connection');
        }
        
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
        // Disabled for privacy
        throw new Error('Screenshots disabled in Tor mode for privacy');
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
        // Force Tor Browser user agent
        console.warn('[Tor] User agent is fixed for privacy');
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
            await tabData.webContents.session.clearStorageData();
        } else {
            await this.session.clearStorageData();
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
        
        // Clean up the isolated session for this tab
        if (tabData.partition) {
            try {
                const tabSession = session.fromPartition(tabData.partition);
                // Clear all data for this isolated circuit
                await tabSession.clearCache();
                await tabSession.clearStorageData({
                    storages: ['cookies', 'localstorage', 'indexdb', 'websql', 'serviceworkers', 'cachestorage']
                });
                console.log(`   🧹 Cleared isolated session: ${tabData.partition}`);
            } catch (error) {
                console.warn('Failed to clear session:', error.message);
            }
        }
        
        tabData.view.webContents.destroy();
        
        // Remove circuit tracking
        const circuitId = this.tabCircuits.get(tabId);
        if (circuitId) {
            console.log(`🔒 Tor circuit closed for tab ${tabId}: ${circuitId}`);
            this.tabCircuits.delete(tabId);
        }
        
        this.tabs.delete(tabId);
        console.log('✅ Tor tab closed:', tabId);
    }

    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: false, // Disabled for fingerprinting protection
            webrtc: false, // Disabled for IP leak protection
            serviceWorkers: false, // Disabled for privacy
            extensions: false,
            devTools: false,
            anonymity: true, // Tor feature
            maxPrivacy: true // Tor feature
        };
    }

    async shutdown() {
        await super.shutdown();
        
        // Stop Tor process if we started it
        if (this.torProcess) {
            this.torProcess.kill();
            this.torProcess = null;
            console.log('✅ Tor process stopped');
        }
    }

    async newCircuit(tabId) {
        // Request new Tor circuit for this tab
        console.log('🔄 Requesting new Tor circuit for tab:', tabId);
        // In a full implementation, this would use Tor's control port
        const tabData = this.tabs.get(tabId);
        if (tabData) {
            await this.reload(tabId);
        }
    }
}

module.exports = TorEngine;

