/**
 * UIManager - Handles UI elements, start page, and visual feedback
 * Simplified UI management without complex loading states
 */

class UIManager {
    constructor() {
        this.startPage = document.getElementById('startPage');
        this.startSearch = document.getElementById('startSearch');
        this.engineSelector = document.getElementById('engineSelector');
        this.engineBadge = document.getElementById('engineBadge');
        
        this.setupEventListeners();
        this.setupStartPage();
    }
    
    setupEventListeners() {
        // Start page search
        if (this.startSearch) {
            this.startSearch.addEventListener('keydown', async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.startSearch.value.trim();
                    if (query) {
                        // Trigger navigation through global navigation manager
                        if (window.navigationManager) {
                            await window.navigationManager.navigateTo(query);
                            this.startSearch.value = '';
                        }
                    }
                }
            });
        }
        
        // Engine selector
        if (this.engineSelector) {
            this.engineSelector.addEventListener('change', (e) => {
                const newEngine = e.target.value;
                this.updateEngineBadge(newEngine);
            });
        }
    }
    
    setupStartPage() {
        // Setup quick links
        this.setupQuickLinks();
        
        // Setup search mode toggle
        this.setupSearchModeToggle();
    }
    
    setupQuickLinks() {
        const quickLinks = document.querySelectorAll('.quick-link');
        quickLinks.forEach(item => {
            item.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const url = item.getAttribute('data-url');
                if (url && window.navigationManager) {
                    await window.navigationManager.navigateTo(url);
                }
            });
        });
    }
    
    setupSearchModeToggle() {
        const searchModeToggle = document.getElementById('searchModeToggle');
        const searchModeIndicator = document.getElementById('searchModeIndicator');
        const searchHint = document.getElementById('searchHint');
        
        if (searchModeToggle && searchModeIndicator && searchHint) {
            searchModeToggle.addEventListener('click', () => {
                const isAIMode = searchModeToggle.classList.contains('ai-mode');
                
                if (isAIMode) {
                    // Switch to normal search
                    searchModeToggle.classList.remove('ai-mode');
                    searchModeIndicator.innerHTML = '🔍';
                    this.startSearch.placeholder = 'Search the web...';
                    searchHint.innerHTML = 'Click 🔍 to toggle AI mode';
                } else {
                    // Switch to AI mode
                    searchModeToggle.classList.add('ai-mode');
                    searchModeIndicator.innerHTML = '✨';
                    this.startSearch.placeholder = 'Ask AI anything...';
                    searchHint.textContent = 'AI Mode Active';
                }
                
                this.startSearch.focus();
            });
        }
    }
    
    showStartPage() {
        if (this.startPage) {
            this.startPage.classList.remove('hidden');
        }
        
        // Clear search
        if (this.startSearch) {
            this.startSearch.value = '';
        }
        
        // Hide other pages
        this.hideAllPages();
    }
    
    hideStartPage() {
        if (this.startPage) {
            this.startPage.classList.add('hidden');
        }
    }
    
    hideAllPages() {
        // Hide all special pages
        const pages = [
            'aiPage',
            'extensionsPage',
            'noInternetPage'
        ];
        
        pages.forEach(pageId => {
            const page = document.getElementById(pageId);
            if (page) {
                page.style.display = 'none';
                page.classList.add('hidden');
            }
        });
    }
    
    updateEngineBadge(engine) {
        if (!this.engineBadge) return;
        
        const engineNames = {
            'prism': 'Prism',
            'chromium': 'Chromium',
            'firefox': 'Firefox',
            'tor': 'Tor'
        };
        
        this.engineBadge.textContent = engineNames[engine] || 'Firefox';
        this.engineBadge.className = 'engine-badge ' + engine;
        
        // Update selector to match
        if (this.engineSelector && this.engineSelector.value !== engine) {
            this.engineSelector.value = engine;
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === 'error' ? '#FF3B30' : type === 'success' ? '#34C759' : '#007AFF'};
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    showNoInternetPage() {
        const noInternetPage = document.getElementById('noInternetPage');
        if (noInternetPage) {
            noInternetPage.classList.remove('hidden');
            this.hideStartPage();
        }
    }
    
    hideNoInternetPage() {
        const noInternetPage = document.getElementById('noInternetPage');
        if (noInternetPage) {
            noInternetPage.classList.add('hidden');
        }
    }
    
    updateSecurityIndicator(url) {
        const securityIndicator = document.getElementById('securityIndicator');
        if (!securityIndicator) return;
        
        const iconElement = securityIndicator.querySelector('.icon');
        if (!iconElement) return;
        
        // Reset classes
        securityIndicator.classList.remove('secure', 'insecure');
        
        if (!url || url === '' || url.startsWith('prism://')) {
            // Internal pages or no URL
            iconElement.innerHTML = '🔒';
            securityIndicator.title = 'Internal page';
            securityIndicator.style.display = 'none';
        } else if (url.startsWith('https://')) {
            // Secure HTTPS connection
            iconElement.innerHTML = '🔒';
            securityIndicator.classList.add('secure');
            securityIndicator.title = 'Secure connection (HTTPS)';
            securityIndicator.style.display = 'flex';
        } else if (url.startsWith('http://')) {
            // Insecure HTTP connection
            iconElement.innerHTML = '⚠️';
            securityIndicator.classList.add('insecure');
            securityIndicator.title = 'Not secure (HTTP)';
            securityIndicator.style.display = 'flex';
        } else {
            // Other protocols
            iconElement.innerHTML = '🔒';
            securityIndicator.title = 'Local resource';
            securityIndicator.style.display = 'none';
        }
    }
}

// Export for use in main renderer
window.UIManager = UIManager;
