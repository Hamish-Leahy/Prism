/**
 * Firefox Extension Manager
 * Loads and manages Firefox extensions (WebExtensions API) for all engines
 * Extensions run independently using Firefox's WebExtension API
 */

const path = require('path');
const fs = require('fs');
const { session, app } = require('electron');

class ExtensionManager {
    constructor() {
        this.extensions = new Map(); // extensionId -> extension info
        
        // Use app data directory instead of asar bundle (which is read-only when packaged)
        const userDataPath = app.getPath('userData');
        this.extensionsDir = path.join(userDataPath, 'extensions', 'installed');
        this.initialized = false;
        
        // Ensure extensions directory exists
        try {
            if (!fs.existsSync(this.extensionsDir)) {
                fs.mkdirSync(this.extensionsDir, { recursive: true });
            }
        } catch (error) {
            console.warn('⚠️ Could not create extensions directory:', error.message);
        }
    }

    async initialize() {
        console.log('🔌 Initializing Firefox Extension Manager...');
        
        try {
            // Load all installed extensions
            await this.loadInstalledExtensions();
            
            // Auto-install uBlock Origin if not already installed
            // DISABLED for v0.1.0 - will enable in future version
            // await this.autoInstallEssentialExtensions();
            
            this.initialized = true;
            console.log(`✅ Extension Manager initialized with ${this.extensions.size} extensions`);
            return true;
        } catch (error) {
            console.error('❌ Extension Manager initialization failed:', error);
            return false;
        }
    }

    /**
     * Auto-install essential privacy and security extensions
     */
    async autoInstallEssentialExtensions() {
        const essentialExtensions = [
            {
                name: 'uBlock Origin',
                slug: 'ublock-origin',
                reason: 'Ad blocking and privacy protection'
            }
        ];

        for (const ext of essentialExtensions) {
            // Check if already installed
            const isInstalled = Array.from(this.extensions.values()).some(
                e => e.name.toLowerCase().includes(ext.slug.replace('-', ' '))
            );

            if (!isInstalled) {
                console.log(`📦 Auto-installing ${ext.name} (${ext.reason})...`);
                try {
                    // Import ExtensionDownloader
                    const ExtensionDownloader = require('./ExtensionDownloader');
                    const downloader = new ExtensionDownloader(this);
                    
                    const addonUrl = `https://addons.mozilla.org/firefox/addon/${ext.slug}/`;
                    await downloader.installFromMozilla(addonUrl);
                    console.log(`✅ ${ext.name} installed successfully`);
                } catch (error) {
                    console.warn(`⚠️ Failed to auto-install ${ext.name}:`, error.message);
                }
            } else {
                console.log(`✓ ${ext.name} already installed`);
            }
        }
    }

    async loadInstalledExtensions() {
        if (!fs.existsSync(this.extensionsDir)) {
            return;
        }

        const extensionDirs = fs.readdirSync(this.extensionsDir, { withFileTypes: true })
            .filter(dirent => dirent.isDirectory())
            .map(dirent => dirent.name);

        for (const dirName of extensionDirs) {
            const extensionPath = path.join(this.extensionsDir, dirName);
            try {
                await this.loadExtension(extensionPath);
            } catch (error) {
                console.error(`Failed to load extension from ${extensionPath}:`, error);
            }
        }
    }

    async loadExtension(extensionPath) {
        // Read manifest.json
        const manifestPath = path.join(extensionPath, 'manifest.json');
        if (!fs.existsSync(manifestPath)) {
            throw new Error('manifest.json not found');
        }

        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
        const extensionId = manifest.browser_specific_settings?.gecko?.id || 
                           manifest.applications?.gecko?.id || 
                           path.basename(extensionPath);

        // Load extension in all sessions
        const sessions = ['chromium', 'firefox', 'tor', 'prism'];
        const loadedSessions = [];

        for (const sessionName of sessions) {
            try {
                const sess = session.fromPartition(`persist:${sessionName}`);
                await sess.loadExtension(extensionPath);
                loadedSessions.push(sessionName);
            } catch (error) {
                console.warn(`Failed to load extension ${extensionId} in ${sessionName}:`, error.message);
            }
        }

        if (loadedSessions.length > 0) {
            this.extensions.set(extensionId, {
                id: extensionId,
                name: manifest.name,
                version: manifest.version,
                description: manifest.description,
                path: extensionPath,
                manifest: manifest,
                loadedIn: loadedSessions
            });

            console.log(`✅ Loaded extension: ${manifest.name} (${extensionId}) in [${loadedSessions.join(', ')}]`);
        } else {
            throw new Error('Failed to load extension in any session');
        }

        return extensionId;
    }

    async installExtension(xpiPath) {
        // XPI is a ZIP file, extract it
        const AdmZip = require('adm-zip');
        const zip = new AdmZip(xpiPath);
        
        // Read manifest to get extension ID
        const manifestEntry = zip.getEntry('manifest.json');
        if (!manifestEntry) {
            throw new Error('Invalid extension: manifest.json not found');
        }

        const manifest = JSON.parse(manifestEntry.getData().toString('utf8'));
        const extensionId = manifest.browser_specific_settings?.gecko?.id || 
                           manifest.applications?.gecko?.id || 
                           `extension-${Date.now()}`;

        // Extract to extensions directory
        const extractPath = path.join(this.extensionsDir, extensionId);
        if (fs.existsSync(extractPath)) {
            // Remove old version
            fs.rmSync(extractPath, { recursive: true, force: true });
        }

        zip.extractAllTo(extractPath, true);

        // Load the extension
        await this.loadExtension(extractPath);

        console.log(`✅ Installed extension: ${manifest.name} (${extensionId})`);
        return extensionId;
    }

    async uninstallExtension(extensionId) {
        const ext = this.extensions.get(extensionId);
        if (!ext) {
            throw new Error('Extension not found: ' + extensionId);
        }

        // Remove extension from all sessions
        for (const sessionName of ext.loadedIn) {
            try {
                const sess = session.fromPartition(`persist:${sessionName}`);
                await sess.removeExtension(extensionId);
            } catch (error) {
                console.warn(`Failed to unload extension from ${sessionName}:`, error.message);
            }
        }

        // Delete extension files
        if (fs.existsSync(ext.path)) {
            fs.rmSync(ext.path, { recursive: true, force: true });
        }

        this.extensions.delete(extensionId);
        console.log(`✅ Uninstalled extension: ${ext.name} (${extensionId})`);
    }

    getExtensions() {
        return Array.from(this.extensions.values());
    }

    getExtension(extensionId) {
        return this.extensions.get(extensionId);
    }

    hasExtension(extensionId) {
        return this.extensions.has(extensionId);
    }

    async enableExtension(extensionId, sessionName = null) {
        const ext = this.extensions.get(extensionId);
        if (!ext) {
            throw new Error('Extension not found: ' + extensionId);
        }

        const sessions = sessionName ? [sessionName] : ['chromium', 'firefox', 'tor', 'prism'];

        for (const sess of sessions) {
            try {
                const session = require('electron').session.fromPartition(`persist:${sess}`);
                await session.loadExtension(ext.path);
                
                if (!ext.loadedIn.includes(sess)) {
                    ext.loadedIn.push(sess);
                }
            } catch (error) {
                console.warn(`Failed to enable extension in ${sess}:`, error.message);
            }
        }

        console.log(`✅ Enabled extension: ${ext.name} in [${sessions.join(', ')}]`);
    }

    /**
     * Load all installed extensions into a specific session partition
     * Useful for dynamic sessions like per-tab Tor circuits
     */
    async loadExtensionsIntoSession(sessionPartition) {
        const sess = require('electron').session.fromPartition(sessionPartition);
        const loadedCount = [];

        for (const [extensionId, ext] of this.extensions) {
            try {
                await sess.loadExtension(ext.path);
                loadedCount.push(ext.name);
            } catch (error) {
                console.warn(`Failed to load ${ext.name} into ${sessionPartition}:`, error.message);
            }
        }

        if (loadedCount.length > 0) {
            console.log(`✅ Loaded ${loadedCount.length} extension(s) into ${sessionPartition}: ${loadedCount.join(', ')}`);
        }
        
        return loadedCount.length;
    }

    async disableExtension(extensionId, sessionName = null) {
        const ext = this.extensions.get(extensionId);
        if (!ext) {
            throw new Error('Extension not found: ' + extensionId);
        }

        const sessions = sessionName ? [sessionName] : ext.loadedIn;

        for (const sess of sessions) {
            try {
                const session = require('electron').session.fromPartition(`persist:${sess}`);
                await session.removeExtension(extensionId);
                
                ext.loadedIn = ext.loadedIn.filter(s => s !== sess);
            } catch (error) {
                console.warn(`Failed to disable extension in ${sess}:`, error.message);
            }
        }

        console.log(`✅ Disabled extension: ${ext.name} in [${sessions.join(', ')}]`);
    }

    getStats() {
        return {
            totalExtensions: this.extensions.size,
            extensions: this.getExtensions().map(ext => ({
                id: ext.id,
                name: ext.name,
                version: ext.version,
                loadedIn: ext.loadedIn
            }))
        };
    }

    async shutdown() {
        console.log('🛑 Shutting down Extension Manager...');
        
        // Extensions are automatically unloaded when sessions are destroyed
        this.extensions.clear();
        
        console.log('✅ Extension Manager shutdown complete');
    }
}

module.exports = ExtensionManager;

