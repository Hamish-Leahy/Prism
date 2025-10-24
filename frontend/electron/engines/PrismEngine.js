/**
 * Prism Engine - Custom lightweight rendering engine
 * Uses server-side rendering via PHP backend
 */

const { BrowserView, session } = require('electron');
const EngineInterface = require('./EngineInterface');
const fetch = require('node-fetch');

class PrismEngine extends EngineInterface {
    constructor(config = {}) {
        super(config);
        this.name = 'prism';
        this.version = '1.0.0';
        this.session = null;
        this.mainWindow = config.mainWindow;
        this.partition = 'persist:prism';
        this.backendUrl = config.backendUrl || 'http://localhost:8000';
    }

    async initialize() {
        try {
            // Create isolated session for Prism engine
            this.session = session.fromPartition(this.partition);
            
            // Set Prism user agent
            this.session.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Prism/1.0.0 (KHTML, like Gecko) Safari/605.1.15');
            
            // Check backend connectivity
            try {
                const response = await fetch(`${this.backendUrl}/api/health`);
                if (!response.ok) {
                    console.warn('⚠️  Prism backend not responding');
                }
            } catch (error) {
                console.warn('⚠️  Prism backend not available:', error.message);
            }

            this.ready = true;
            console.log('✅ Prism Engine initialized (v' + this.version + ')');
            console.log('   Backend:', this.backendUrl);
            return true;
        } catch (error) {
            console.error('❌ Prism Engine initialization failed:', error);
            return false;
        }
    }

    async createTab(tabId, options = {}) {
        if (!this.ready) {
            throw new Error('Prism engine not initialized');
        }

        if (this.tabs.has(tabId)) {
            throw new Error('Tab already exists: ' + tabId);
        }

        // Create BrowserView for displaying rendered content
        const view = new BrowserView({
            webPreferences: {
                partition: this.partition,
                nodeIntegration: false,
                contextIsolation: true,
                sandbox: true,
                webSecurity: false, // Allow loading from data URLs
                allowRunningInsecureContent: false,
                experimentalFeatures: true,
                webgl: true,
                plugins: false
            }
        });

        const webContents = view.webContents;
        webContents.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Prism/1.0.0 (KHTML, like Gecko) Safari/605.1.15');

        const tabData = {
            view: view,
            webContents: webContents,
            title: 'Prism Tab',
            url: '',
            loading: false,
            canGoBack: false,
            canGoForward: false,
            visible: false,
            history: [],
            historyIndex: -1
        };

        this.tabs.set(tabId, tabData);
        this.setupEventListeners(tabId, tabData);

        console.log('✅ Prism tab created:', tabId);
        return {
            success: true,
            tabId: tabId,
            engine: 'prism'
        };
    }

    setupEventListeners(tabId, tabData) {
        const { webContents } = tabData;

        webContents.on('did-start-loading', () => {
            tabData.loading = true;
            this.emit('loading-start', { tabId, engine: 'prism' });
        });

        webContents.on('did-stop-loading', () => {
            tabData.loading = false;
            this.emit('loading-stop', { 
                tabId, 
                engine: 'prism',
                title: tabData.title,
                url: tabData.url
            });
        });

        webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
            console.error('Prism load failed:', errorDescription);
            this.emit('load-error', { 
                tabId, 
                engine: 'prism',
                error: errorDescription 
            });
        });

        webContents.on('page-title-updated', (event, title) => {
            tabData.title = title;
            this.emit('title-updated', { tabId, engine: 'prism', title });
        });
    }

    emit(event, data) {
        if (this.config.eventHandler) {
            this.config.eventHandler(event, data);
        }
    }

    async navigate(tabId, url) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);

        tabData.loading = true;
        this.emit('loading-start', { tabId, engine: 'prism' });

        try {
            // Handle prism:// protocol
            if (url.startsWith('prism://')) {
                await this.handlePrismProtocol(tabId, url);
                return;
            }

            // Fetch rendered content from backend
            const response = await fetch(`${this.backendUrl}/api/engine/navigate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url, engine: 'prism' })
            });

            if (!response.ok) {
                throw new Error('Backend request failed: ' + response.statusText);
            }

            const result = await response.json();

            if (result.success) {
                // Load rendered content
                const dataUrl = 'data:text/html;charset=utf-8,' + encodeURIComponent(result.content);
                tabData.webContents.loadURL(dataUrl);
                
                // Update tab data
                tabData.url = result.url || url;
                tabData.title = result.title || 'Prism Page';
                
                // Add to history
                tabData.history = tabData.history.slice(0, tabData.historyIndex + 1);
                tabData.history.push({ url: tabData.url, title: tabData.title });
                tabData.historyIndex = tabData.history.length - 1;
                
                console.log('✅ Prism navigated to:', tabData.url);
            } else {
                throw new Error(result.error || 'Navigation failed');
            }
        } catch (error) {
            console.error('Prism navigation error:', error);
            
            // Fallback: display error page
            const errorHtml = this.generateErrorPage(url, error.message);
            const dataUrl = 'data:text/html;charset=utf-8,' + encodeURIComponent(errorHtml);
            tabData.webContents.loadURL(dataUrl);
            
            tabData.title = 'Error';
            tabData.url = url;
        } finally {
            tabData.loading = false;
            this.emit('loading-stop', { 
                tabId, 
                engine: 'prism',
                title: tabData.title,
                url: tabData.url
            });
        }
    }

    async handlePrismProtocol(tabId, url) {
        const tabData = this.tabs.get(tabId);
        
        // Parse prism:// URL
        const path = url.replace('prism://', '');
        
        if (path === 'home' || path === '') {
            // Show Prism home page
            const html = this.generateHomePage();
            const dataUrl = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
            tabData.webContents.loadURL(dataUrl);
            tabData.title = 'Prism Home';
            tabData.url = 'prism://home';
        } else if (path.startsWith('search?q=')) {
            // Handle search
            const query = decodeURIComponent(path.replace('search?q=', ''));
            await this.performSearch(tabId, query);
        } else {
            // Unknown protocol path
            const html = this.generate404Page(path);
            const dataUrl = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
            tabData.webContents.loadURL(dataUrl);
            tabData.title = '404 - Not Found';
            tabData.url = url;
        }
    }

    async performSearch(tabId, query) {
        const tabData = this.tabs.get(tabId);
        
        try {
            const response = await fetch(`${this.backendUrl}/api/search?q=` + encodeURIComponent(query));
            const result = await response.json();
            
            if (result.success) {
                const html = this.generateSearchResults(query, result.results || []);
                const dataUrl = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
                tabData.webContents.loadURL(dataUrl);
                tabData.title = `Search: ${query}`;
                tabData.url = `prism://search?q=${encodeURIComponent(query)}`;
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    generateHomePage() {
        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prism Home</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            text-align: center;
            color: white;
        }
        h1 {
            font-size: 4em;
            margin: 0;
            font-weight: 700;
        }
        p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        .search-box {
            margin: 40px auto;
            width: 600px;
        }
        input {
            width: 100%;
            padding: 16px 20px;
            font-size: 16px;
            border: none;
            border-radius: 30px;
            outline: none;
        }
        .features {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin-top: 50px;
        }
        .feature {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔮 Prism</h1>
        <p>Your Custom Browser Engine</p>
        <div class="search-box">
            <input type="text" placeholder="Search or enter URL" id="searchInput">
        </div>
        <div class="features">
            <div class="feature">⚡ Fast</div>
            <div class="feature">🔒 Private</div>
            <div class="feature">🎨 Custom</div>
        </div>
    </div>
    <script>
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const query = e.target.value;
                if (query.includes('.') && !query.includes(' ')) {
                    window.location = 'https://' + query;
                } else {
                    window.location = 'prism://search?q=' + encodeURIComponent(query);
                }
            }
        });
    </script>
</body>
</html>
        `;
    }

    generateSearchResults(query, results) {
        const resultsHtml = results.map(r => `
            <div class="result">
                <a href="${r.url}"><h3>${r.title}</h3></a>
                <p>${r.description}</p>
                <div class="url">${r.url}</div>
            </div>
        `).join('');

        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Search: ${query}</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .result {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result h3 {
            margin: 0 0 10px 0;
        }
        .result a {
            color: #667eea;
            text-decoration: none;
        }
        .url {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔮 Search Results for "${query}"</h1>
    </div>
    ${resultsHtml || '<p>No results found.</p>'}
</body>
</html>
        `;
    }

    generateErrorPage(url, error) {
        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Error</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 {
            color: #e74c3c;
        }
        p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="error">
        <h1>⚠️ Page Load Error</h1>
        <p><strong>URL:</strong> ${url}</p>
        <p><strong>Error:</strong> ${error}</p>
    </div>
</body>
</html>
        `;
    }

    generate404Page(path) {
        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>404 - Not Found</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: white;
        }
        .content {
            text-align: center;
        }
        h1 {
            font-size: 6em;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>404</h1>
        <p>Prism protocol path not found: ${path}</p>
    </div>
</body>
</html>
        `;
    }

    async goBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        
        if (tabData.historyIndex > 0) {
            tabData.historyIndex--;
            const historyItem = tabData.history[tabData.historyIndex];
            await this.navigate(tabId, historyItem.url);
        }
    }

    async goForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        
        if (tabData.historyIndex < tabData.history.length - 1) {
            tabData.historyIndex++;
            const historyItem = tabData.history[tabData.historyIndex];
            await this.navigate(tabId, historyItem.url);
        }
    }

    async reload(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        
        if (tabData.url) {
            await this.navigate(tabId, tabData.url);
        }
    }

    async stop(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        tabData.loading = false;
    }

    async executeJavaScript(tabId, code) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return await tabData.webContents.executeJavaScript(code);
    }

    async getTitle(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.title;
    }

    async getURL(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.url;
    }

    async canGoBack(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.historyIndex > 0;
    }

    async canGoForward(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.historyIndex < tabData.history.length - 1;
    }

    async isLoading(tabId) {
        const tabData = this.tabs.get(tabId);
        if (!tabData) throw new Error('Tab not found: ' + tabId);
        return tabData.loading;
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
        // Prism uses fixed user agent
        console.warn('[Prism] User agent is fixed');
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
        return [];
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
        console.log('✅ Prism tab closed:', tabId);
    }

    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: true,
            webrtc: false,
            serviceWorkers: false,
            extensions: false,
            devTools: false,
            customRendering: true, // Prism feature
            serverSideRendering: true, // Prism feature
            prismProtocol: true // Prism feature
        };
    }
}

module.exports = PrismEngine;

