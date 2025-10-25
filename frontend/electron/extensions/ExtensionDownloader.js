/**
 * Firefox Extension Downloader
 * Downloads .xpi files from Firefox Add-ons store (addons.mozilla.org)
 * and automatically installs them
 */

const https = require('https');
const fs = require('fs');
const path = require('path');
const { app } = require('electron');

class ExtensionDownloader {
    constructor(extensionManager) {
        this.extensionManager = extensionManager;
        this.downloadsDir = path.join(app.getPath('downloads'), 'prism-extensions');
        this.tempDir = path.join(app.getPath('temp'), 'prism-extension-downloads');
        
        // Ensure directories exist
        if (!fs.existsSync(this.downloadsDir)) {
            fs.mkdirSync(this.downloadsDir, { recursive: true });
        }
        if (!fs.existsSync(this.tempDir)) {
            fs.mkdirSync(this.tempDir, { recursive: true });
        }
    }

    /**
     * Download extension from URL (typically from addons.mozilla.org)
     * @param {string} downloadUrl - Direct download URL for .xpi file
     * @param {function} progressCallback - Optional callback for download progress
     * @returns {Promise<string>} - Path to downloaded .xpi file
     */
    async downloadExtension(downloadUrl, progressCallback = null) {
        return new Promise((resolve, reject) => {
            console.log('📥 Downloading extension from:', downloadUrl);
            
            const fileName = `extension-${Date.now()}.xpi`;
            const filePath = path.join(this.tempDir, fileName);
            const file = fs.createWriteStream(filePath);
            
            https.get(downloadUrl, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0'
                }
            }, (response) => {
                // Handle redirects
                if (response.statusCode === 301 || response.statusCode === 302) {
                    const redirectUrl = response.headers.location;
                    console.log('📍 Redirecting to:', redirectUrl);
                    file.close();
                    fs.unlinkSync(filePath);
                    return this.downloadExtension(redirectUrl, progressCallback)
                        .then(resolve)
                        .catch(reject);
                }
                
                if (response.statusCode !== 200) {
                    file.close();
                    fs.unlinkSync(filePath);
                    return reject(new Error(`Failed to download: ${response.statusCode}`));
                }
                
                const totalSize = parseInt(response.headers['content-length'], 10);
                let downloadedSize = 0;
                
                response.on('data', (chunk) => {
                    downloadedSize += chunk.length;
                    if (progressCallback && totalSize) {
                        const progress = (downloadedSize / totalSize) * 100;
                        progressCallback(progress, downloadedSize, totalSize);
                    }
                });
                
                response.pipe(file);
                
                file.on('finish', () => {
                    file.close();
                    console.log('✅ Extension downloaded:', filePath);
                    resolve(filePath);
                });
            }).on('error', (err) => {
                fs.unlink(filePath, () => {});
                reject(err);
            });
        });
    }

    /**
     * Download and install extension from Firefox Add-ons store
     * @param {string} addonUrl - URL to the addon page (e.g., https://addons.mozilla.org/firefox/addon/ublock-origin/)
     * @param {function} progressCallback - Optional callback for download progress
     * @returns {Promise<string>} - Installed extension ID
     */
    async installFromMozilla(addonUrl, progressCallback = null) {
        try {
            // Extract addon slug from URL
            const slug = this.extractAddonSlug(addonUrl);
            
            // Get addon details from Mozilla API
            const addonData = await this.getAddonDetails(slug);
            
            // Get download URL for the latest version
            if (!addonData.current_version || !addonData.current_version.files || addonData.current_version.files.length === 0) {
                throw new Error('No download available for this extension');
            }
            const downloadUrl = addonData.current_version.files[0].url;
            
            console.log(`📦 Installing ${addonData.name.en-US || addonData.name}...`);
            
            // Download the .xpi file
            const xpiPath = await this.downloadExtension(downloadUrl, progressCallback);
            
            // Install the extension
            const extensionId = await this.extensionManager.installExtension(xpiPath);
            
            // Clean up temp file
            fs.unlinkSync(xpiPath);
            
            console.log(`✅ Successfully installed ${addonData.name.en-US || addonData.name}`);
            
            return {
                extensionId,
                name: addonData.name['en-US'] || addonData.name,
                description: addonData.summary['en-US'] || addonData.summary,
                version: addonData.current_version.version,
                author: addonData.authors[0]?.name || 'Unknown',
                icon: addonData.icon_url
            };
        } catch (error) {
            console.error('❌ Failed to install extension:', error);
            throw error;
        }
    }

    /**
     * Get addon details from Mozilla API
     * @param {string} slug - Addon slug (e.g., 'ublock-origin')
     * @returns {Promise<object>} - Addon metadata
     */
    async getAddonDetails(slug) {
        return new Promise((resolve, reject) => {
            const apiUrl = `https://addons.mozilla.org/api/v5/addons/addon/${slug}/`;
            
            https.get(apiUrl, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0'
                }
            }, (response) => {
                let data = '';
                
                response.on('data', (chunk) => {
                    data += chunk;
                });
                
                response.on('end', () => {
                    try {
                        const addonData = JSON.parse(data);
                        resolve(addonData);
                    } catch (error) {
                        reject(new Error('Failed to parse addon data'));
                    }
                });
            }).on('error', reject);
        });
    }

    /**
     * Extract addon slug from Mozilla addon URL
     * @param {string} url - Addon URL
     * @returns {string} - Addon slug
     */
    extractAddonSlug(url) {
        // Handle different URL formats
        // https://addons.mozilla.org/en-US/firefox/addon/ublock-origin/
        // https://addons.mozilla.org/firefox/addon/ublock-origin/
        const match = url.match(/\/addon\/([^\/]+)/);
        if (match) {
            return match[1];
        }
        throw new Error('Invalid Mozilla addon URL');
    }

    /**
     * Search Mozilla Add-ons store
     * @param {string} query - Search query
     * @param {number} limit - Number of results (default: 10)
     * @returns {Promise<Array>} - Array of addon results
     */
    async searchAddons(query, limit = 10) {
        return new Promise((resolve, reject) => {
            const searchUrl = `https://addons.mozilla.org/api/v5/addons/search/?q=${encodeURIComponent(query)}&page_size=${limit}&type=extension`;
            
            https.get(searchUrl, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0'
                }
            }, (response) => {
                let data = '';
                
                response.on('data', (chunk) => {
                    data += chunk;
                });
                
                response.on('end', () => {
                    try {
                        const results = JSON.parse(data);
                        const addons = results.results.map(addon => ({
                            slug: addon.slug,
                            name: addon.name['en-US'] || addon.name,
                            description: addon.summary['en-US'] || addon.summary,
                            icon: addon.icon_url,
                            author: addon.authors[0]?.name || 'Unknown',
                            rating: addon.ratings?.average || 0,
                            users: addon.average_daily_users || 0,
                            url: addon.url
                        }));
                        resolve(addons);
                    } catch (error) {
                        reject(new Error('Failed to parse search results'));
                    }
                });
            }).on('error', reject);
        });
    }

    /**
     * Get popular/featured extensions
     * @param {number} limit - Number of results (default: 20)
     * @returns {Promise<Array>} - Array of popular addons
     */
    async getFeaturedAddons(limit = 20) {
        return new Promise((resolve, reject) => {
            const apiUrl = `https://addons.mozilla.org/api/v5/addons/search/?featured=true&page_size=${limit}&type=extension&sort=users`;
            
            https.get(apiUrl, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0'
                }
            }, (response) => {
                let data = '';
                
                response.on('data', (chunk) => {
                    data += chunk;
                });
                
                response.on('end', () => {
                    try {
                        const results = JSON.parse(data);
                        const addons = results.results.map(addon => ({
                            slug: addon.slug,
                            name: addon.name['en-US'] || addon.name,
                            description: addon.summary['en-US'] || addon.summary,
                            icon: addon.icon_url,
                            author: addon.authors[0]?.name || 'Unknown',
                            rating: addon.ratings?.average || 0,
                            users: addon.average_daily_users || 0,
                            url: addon.url
                        }));
                        resolve(addons);
                    } catch (error) {
                        reject(new Error('Failed to parse featured addons'));
                    }
                });
            }).on('error', reject);
        });
    }

    cleanup() {
        // Clean up temp directory
        if (fs.existsSync(this.tempDir)) {
            fs.rmSync(this.tempDir, { recursive: true, force: true });
        }
    }
}

module.exports = ExtensionDownloader;

