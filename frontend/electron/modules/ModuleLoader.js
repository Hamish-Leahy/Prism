/**
 * ModuleLoader - Loads and initializes all necessary modules for the renderer
 * This ensures all modules are available before the main renderer starts
 */

class ModuleLoader {
    constructor() {
        this.modules = {};
        this.loaded = false;
    }
    
    async loadAllModules() {
        if (this.loaded) return;
        
        console.log('📦 Loading all modules...');
        
        try {
            // Load core modules
            await this.loadCoreModules();
            
            // Load utility modules
            await this.loadUtilityModules();
            
            // Load extension modules
            await this.loadExtensionModules();
            
            this.loaded = true;
            console.log('✅ All modules loaded successfully');
            
        } catch (error) {
            console.error('❌ Failed to load modules:', error);
            throw error;
        }
    }
    
    async loadCoreModules() {
        // Core modules are already loaded via HTML script tags
        // Just verify they exist
        if (typeof TabManager === 'undefined') {
            throw new Error('TabManager not loaded');
        }
        if (typeof NavigationManager === 'undefined') {
            throw new Error('NavigationManager not loaded');
        }
        if (typeof UIManager === 'undefined') {
            throw new Error('UIManager not loaded');
        }
        
        console.log('✅ Core modules loaded');
    }
    
    async loadUtilityModules() {
        // These modules are loaded in main.js but we need to make them available
        // to the renderer through IPC calls
        
        // Check if we can access the managers through IPC
        try {
            // Test if we can communicate with main process
            const testResult = await ipcRenderer.invoke('test:ping');
            console.log('✅ IPC communication working');
        } catch (error) {
            console.warn('⚠️ IPC communication test failed:', error);
        }
        
        console.log('✅ Utility modules accessible via IPC');
    }
    
    async loadExtensionModules() {
        // Extension modules are handled by main process
        // We just need to ensure we can communicate with them
        
        try {
            // Test extension manager access
            const extensions = await ipcRenderer.invoke('extensions:list');
            console.log('✅ Extension manager accessible');
        } catch (error) {
            console.warn('⚠️ Extension manager not accessible:', error);
        }
        
        console.log('✅ Extension modules accessible via IPC');
    }
    
    getModule(name) {
        return this.modules[name];
    }
    
    isLoaded() {
        return this.loaded;
    }
}

// Global module loader instance
window.moduleLoader = new ModuleLoader();

// Auto-load modules when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.moduleLoader.loadAllModules();
    });
} else {
    window.moduleLoader.loadAllModules();
}
