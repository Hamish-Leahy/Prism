/**
 * Base Engine Interface
 * All browser engines must implement this interface
 */

class EngineInterface {
    constructor(config = {}) {
        this.config = config;
        this.name = 'base';
        this.version = '1.0.0';
        this.ready = false;
        this.tabs = new Map();
    }

    /**
     * Initialize the engine
     * @returns {Promise<boolean>}
     */
    async initialize() {
        throw new Error('initialize() must be implemented by engine');
    }

    /**
     * Create a new tab/view
     * @param {string} tabId - Unique tab identifier
     * @param {Object} options - Tab creation options
     * @returns {Promise<Object>}
     */
    async createTab(tabId, options = {}) {
        throw new Error('createTab() must be implemented by engine');
    }

    /**
     * Navigate to URL
     * @param {string} tabId - Tab identifier
     * @param {string} url - URL to navigate to
     * @returns {Promise<void>}
     */
    async navigate(tabId, url) {
        throw new Error('navigate() must be implemented by engine');
    }

    /**
     * Go back in history
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async goBack(tabId) {
        throw new Error('goBack() must be implemented by engine');
    }

    /**
     * Go forward in history
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async goForward(tabId) {
        throw new Error('goForward() must be implemented by engine');
    }

    /**
     * Reload the page
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async reload(tabId) {
        throw new Error('reload() must be implemented by engine');
    }

    /**
     * Stop loading
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async stop(tabId) {
        throw new Error('stop() must be implemented by engine');
    }

    /**
     * Execute JavaScript in tab
     * @param {string} tabId - Tab identifier
     * @param {string} code - JavaScript code to execute
     * @returns {Promise<any>}
     */
    async executeJavaScript(tabId, code) {
        throw new Error('executeJavaScript() must be implemented by engine');
    }

    /**
     * Get tab title
     * @param {string} tabId - Tab identifier
     * @returns {Promise<string>}
     */
    async getTitle(tabId) {
        throw new Error('getTitle() must be implemented by engine');
    }

    /**
     * Get current URL
     * @param {string} tabId - Tab identifier
     * @returns {Promise<string>}
     */
    async getURL(tabId) {
        throw new Error('getURL() must be implemented by engine');
    }

    /**
     * Check if can go back
     * @param {string} tabId - Tab identifier
     * @returns {Promise<boolean>}
     */
    async canGoBack(tabId) {
        throw new Error('canGoBack() must be implemented by engine');
    }

    /**
     * Check if can go forward
     * @param {string} tabId - Tab identifier
     * @returns {Promise<boolean>}
     */
    async canGoForward(tabId) {
        throw new Error('canGoForward() must be implemented by engine');
    }

    /**
     * Check if loading
     * @param {string} tabId - Tab identifier
     * @returns {Promise<boolean>}
     */
    async isLoading(tabId) {
        throw new Error('isLoading() must be implemented by engine');
    }

    /**
     * Take screenshot
     * @param {string} tabId - Tab identifier
     * @returns {Promise<Buffer>}
     */
    async takeScreenshot(tabId) {
        throw new Error('takeScreenshot() must be implemented by engine');
    }

    /**
     * Set zoom level
     * @param {string} tabId - Tab identifier
     * @param {number} level - Zoom level
     * @returns {Promise<void>}
     */
    async setZoom(tabId, level) {
        throw new Error('setZoom() must be implemented by engine');
    }

    /**
     * Get zoom level
     * @param {string} tabId - Tab identifier
     * @returns {Promise<number>}
     */
    async getZoom(tabId) {
        throw new Error('getZoom() must be implemented by engine');
    }

    /**
     * Set user agent
     * @param {string} tabId - Tab identifier
     * @param {string} userAgent - User agent string
     * @returns {Promise<void>}
     */
    async setUserAgent(tabId, userAgent) {
        throw new Error('setUserAgent() must be implemented by engine');
    }

    /**
     * Clear cache
     * @param {string} tabId - Tab identifier (optional, if not provided clears all)
     * @returns {Promise<void>}
     */
    async clearCache(tabId = null) {
        throw new Error('clearCache() must be implemented by engine');
    }

    /**
     * Clear cookies
     * @param {string} tabId - Tab identifier (optional, if not provided clears all)
     * @returns {Promise<void>}
     */
    async clearCookies(tabId = null) {
        throw new Error('clearCookies() must be implemented by engine');
    }

    /**
     * Get cookies
     * @param {string} tabId - Tab identifier
     * @returns {Promise<Array>}
     */
    async getCookies(tabId) {
        throw new Error('getCookies() must be implemented by engine');
    }

    /**
     * Set cookie
     * @param {string} tabId - Tab identifier
     * @param {Object} cookie - Cookie object
     * @returns {Promise<void>}
     */
    async setCookie(tabId, cookie) {
        throw new Error('setCookie() must be implemented by engine');
    }

    /**
     * Show tab view
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async showTab(tabId) {
        throw new Error('showTab() must be implemented by engine');
    }

    /**
     * Hide tab view
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async hideTab(tabId) {
        throw new Error('hideTab() must be implemented by engine');
    }

    /**
     * Close tab
     * @param {string} tabId - Tab identifier
     * @returns {Promise<void>}
     */
    async closeTab(tabId) {
        throw new Error('closeTab() must be implemented by engine');
    }

    /**
     * Get engine info
     * @returns {Object}
     */
    getInfo() {
        return {
            name: this.name,
            version: this.version,
            ready: this.ready,
            tabCount: this.tabs.size,
            capabilities: this.getCapabilities()
        };
    }

    /**
     * Get engine capabilities
     * @returns {Object}
     */
    getCapabilities() {
        return {
            javascript: true,
            css: true,
            html5: true,
            webgl: true,
            webrtc: true,
            serviceWorkers: true,
            extensions: false,
            devTools: false
        };
    }

    /**
     * Shutdown the engine
     * @returns {Promise<void>}
     */
    async shutdown() {
        // Close all tabs
        for (const tabId of this.tabs.keys()) {
            await this.closeTab(tabId);
        }
        this.tabs.clear();
        this.ready = false;
    }

    /**
     * Get engine statistics
     * @returns {Object}
     */
    getStats() {
        return {
            name: this.name,
            version: this.version,
            tabCount: this.tabs.size,
            memoryUsage: process.memoryUsage(),
            uptime: process.uptime()
        };
    }
}

module.exports = EngineInterface;

