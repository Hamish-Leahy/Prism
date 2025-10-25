/**
 * Firefox Stealth Mode - Preload Script
 * Injected BEFORE any page content loads to hide automation
 */

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
        '__fxdriver_unwrapped',
        '__webdriver_script_fn',
        'webdriver'
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
    // Firefox shouldn't have chrome object
    delete window.chrome;
    
    // ===== Firefox-Specific Properties =====
    
    // Add Mozilla-specific window properties
    window.mozInnerScreenX = window.screenX;
    window.mozInnerScreenY = window.screenY;
    window.mozPaintCount = 0;
    
    // ===== Navigator Hardening =====
    
    // User Agent - ensure consistency
    Object.defineProperty(navigator, 'userAgent', {
        get: () => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0',
        configurable: true
    });
    
    Object.defineProperty(navigator, 'appVersion', {
        get: () => '5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0',
        configurable: true
    });
    
    Object.defineProperty(navigator, 'vendor', {
        get: () => '',  // Firefox has empty vendor
        configurable: true
    });
    
    // Platform
    Object.defineProperty(navigator, 'platform', {
        get: () => 'MacIntel',
        configurable: true
    });
    
    // Languages - realistic
    Object.defineProperty(navigator, 'languages', {
        get: () => ['en-US', 'en'],
        configurable: true
    });
    
    // Hardware - realistic M1/M2 Mac values
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        get: () => 10,
        configurable: true
    });
    
    // Device memory (realistic)
    Object.defineProperty(navigator, 'deviceMemory', {
        get: () => 8,
        configurable: true
    });
    
    // Max touch points
    Object.defineProperty(navigator, 'maxTouchPoints', {
        get: () => 0,
        configurable: true
    });
    
    // Remove headless indicator
    Object.defineProperty(navigator, 'headless', {
        get: () => undefined,
        configurable: true
    });
    
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
        },
        configurable: true
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
        },
        configurable: true
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
    
    const screenWidth = window.screen.width;
    const screenHeight = window.screen.height;
    const availWidth = window.screen.availWidth;
    const availHeight = window.screen.availHeight;
    
    Object.defineProperty(window.screen, 'width', {
        get: () => screenWidth,
        configurable: true
    });
    
    Object.defineProperty(window.screen, 'height', {
        get: () => screenHeight,
        configurable: true
    });
    
    Object.defineProperty(window.screen, 'availWidth', {
        get: () => availWidth,
        configurable: true
    });
    
    Object.defineProperty(window.screen, 'availHeight', {
        get: () => availHeight,
        configurable: true
    });
    
    Object.defineProperty(window.screen, 'colorDepth', {
        get: () => 24,
        configurable: true
    });
    
    Object.defineProperty(window.screen, 'pixelDepth', {
        get: () => 24,
        configurable: true
    });
    
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
    
    // ===== Connection Information =====
    
    if (navigator.connection) {
        Object.defineProperty(navigator.connection, 'rtt', {
            get: () => 50 + Math.floor(Math.random() * 50), // 50-100ms
            configurable: true
        });
        
        Object.defineProperty(navigator.connection, 'downlink', {
            get: () => 10 + Math.random() * 5, // 10-15 Mbps
            configurable: true
        });
        
        Object.defineProperty(navigator.connection, 'effectiveType', {
            get: () => '4g',
            configurable: true
        });
    }
    
    // ===== Ensure window.opener is null =====
    
    if (!window.opener) {
        Object.defineProperty(window, 'opener', {
            get: () => null,
            configurable: true
        });
    }
    
    console.log('[Firefox Stealth Preload] ✓ Anti-detection active before page load');
    
})();

