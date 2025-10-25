const { app, BrowserWindow, Menu, shell, ipcMain, session } = require('electron')
const path = require('path')
const EngineManager = require('./engines/EngineManager')
const ExtensionManager = require('./extensions/ExtensionManager')
const ExtensionDownloader = require('./extensions/ExtensionDownloader')
const DataManager = require('./storage/DataManager')
const DependencyManager = require('./utils/DependencyManager')

// Set app name and dock behavior
app.setName('Prism')
app.name = 'Prism'

// ===== Anti-Detection: Command Line Switches =====
// These switches help avoid bot detection by making Electron look more like a normal browser

// Disable automation flags
app.commandLine.appendSwitch('disable-blink-features', 'AutomationControlled')

// Enable features that real browsers have
app.commandLine.appendSwitch('enable-features', 'NetworkService,NetworkServiceInProcess')

// Disable features that might indicate automation
app.commandLine.appendSwitch('disable-features', 'IsolateOrigins,site-per-process')

// Make hardware acceleration more realistic
app.commandLine.appendSwitch('use-gl', 'desktop')

// Disable logging that might slow things down or look suspicious
app.commandLine.appendSwitch('disable-dev-shm-usage')
app.commandLine.appendSwitch('disable-setuid-sandbox')

// Make the renderer more like a normal browser
app.commandLine.appendSwitch('disable-web-security', false)

// ===== Performance Optimizations =====
// GPU acceleration for better performance
app.commandLine.appendSwitch('enable-gpu-rasterization')
app.commandLine.appendSwitch('enable-accelerated-video-decode')
app.commandLine.appendSwitch('enable-accelerated-2d-canvas')
app.commandLine.appendSwitch('ignore-gpu-blacklist')
app.commandLine.appendSwitch('num-raster-threads', '4')

// Frame synchronization to reduce jitter
app.commandLine.appendSwitch('enable-begin-frame-scheduling')
app.commandLine.appendSwitch('disable-features', 'TouchpadOverscrollHistoryNavigation')

// Better performance without breaking rendering
app.commandLine.appendSwitch('disable-background-timer-throttling')
app.commandLine.appendSwitch('disable-renderer-backgrounding')

// Memory optimization
app.commandLine.appendSwitch('renderer-process-limit', '100')

// High DPI support for better rendering
app.commandLine.appendSwitch('force-device-scale-factor', '1')

// ===== STANDARD BROWSER SMOOTH SCROLLING =====
// Use standard browser smooth scrolling (like Chrome/Firefox)
app.commandLine.appendSwitch('enable-smooth-scrolling')
app.commandLine.appendSwitch('enable-features', 'SmoothScrolling')

// Standard performance optimizations
app.commandLine.appendSwitch('enable-features', 'VaapiVideoDecoder')
app.commandLine.appendSwitch('enable-features', 'VaapiVideoEncoder')

// Disable background throttling for better performance
app.commandLine.appendSwitch('disable-background-timer-throttling')
app.commandLine.appendSwitch('disable-renderer-backgrounding')

console.log('[Anti-Detection] Command line switches applied')
console.log('[Performance] Hardware acceleration enabled')

let mainWindow
let engineManager
let extensionManager
let extensionDownloader
let dataManager
let dependencyManager

// Configure engine-specific sessions
function setupEngineSessions() {
  // Chromium session (default) with DRM support
  const chromiumSession = session.fromPartition('persist:chromium')
  chromiumSession.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36')
  
  // Enable Widevine DRM (for Netflix, Spotify, Disney+, etc.)
  chromiumSession.setPermissionRequestHandler((webContents, permission, callback) => {
    // Allow protected media (DRM), publickey-credentials (Passkeys/WebAuthn)
    if (permission === 'media' || permission === 'protectedMedia' || permission === 'publickey-credentials-get' || permission === 'publickey-credentials-create') {
      return callback(true)
    }
    // Default: prompt for other permissions
    callback(false)
  })
  
  // Enable iCloud Keychain / Passkey support
  chromiumSession.webRequest.onBeforeSendHeaders((details, callback) => {
    // Allow WebAuthn/Passkey requests
    callback({ requestHeaders: details.requestHeaders })
  })
  
  // Firefox session with DRM support
  const firefoxSession = session.fromPartition('persist:firefox')
  firefoxSession.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/131.0')
  firefoxSession.setPreloads([])
  
  // Enable DRM and Passkeys for Firefox session
  firefoxSession.setPermissionRequestHandler((webContents, permission, callback) => {
    if (permission === 'media' || permission === 'protectedMedia' || permission === 'publickey-credentials-get' || permission === 'publickey-credentials-create') {
      return callback(true)
    }
    callback(false)
  })
  
  // Enable iCloud Keychain / Passkey support for Firefox
  firefoxSession.webRequest.onBeforeSendHeaders((details, callback) => {
    callback({ requestHeaders: details.requestHeaders })
  })
  
  // Tor session with proxy
  const torSession = session.fromPartition('persist:tor')
  torSession.setUserAgent('Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0')
  
  // Configure Tor proxy (SOCKS5 on localhost:9050)
  // Note: User needs to have Tor running locally
  torSession.setProxy({
    proxyRules: 'socks5://127.0.0.1:9050',
    proxyBypassRules: '<local>'
  }).then(() => {
    console.log('Tor proxy configured')
  }).catch((err) => {
    console.warn('Tor proxy not available:', err.message)
    console.log('To use Tor, install and start Tor service on port 9050')
  })
  
  // Enhanced privacy settings for Tor
  torSession.setPermissionRequestHandler((webContents, permission, callback) => {
    // Deny all permission requests for privacy
    if (permission === 'media' || permission === 'geolocation' || permission === 'notifications') {
      return callback(false)
    }
    callback(true)
  })
  
  // Prism session (custom engine)
  const prismSession = session.fromPartition('persist:prism')
  prismSession.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Prism/1.0.0 (KHTML, like Gecko) Safari/605.1.15')
  
  console.log('Engine sessions configured: chromium, firefox, tor, prism')
}

function createWindow() {
  // Setup engine sessions
  setupEngineSessions()
  
  // Set dock icon (macOS) - with error handling
  if (process.platform === 'darwin') {
    const iconPath = path.join(__dirname, 'icon.svg')
    const fs = require('fs')
    if (fs.existsSync(iconPath)) {
      try {
        const nativeImage = require('electron').nativeImage
        const icon = nativeImage.createFromPath(iconPath)
        if (!icon.isEmpty()) {
          app.dock.setIcon(icon)
        }
      } catch (err) {
        console.warn('Could not set dock icon:', err.message)
      }
    }
  }
  
  // Create the main browser window with AGGRESSIVE performance optimizations
  mainWindow = new BrowserWindow({
    width: 1600,
    height: 1000,
    minWidth: 1200,
    minHeight: 800,
    title: 'Prism',
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
      enableRemoteModule: true,
      webSecurity: false, // Needed for cross-origin content
      webviewTag: true,
      // preload: path.join(__dirname, 'preload.js'), // Not needed with nodeIntegration: true
      // DRM Support (Widevine for Netflix, Spotify, etc.)
      plugins: true,
      // Security features
      sandbox: false, // Need this for BrowserView
      allowRunningInsecureContent: false,
      experimentalFeatures: true,
      enableWebSQL: false, // Deprecated, disable for security
      // Media features
      enableBlinkFeatures: 'MediaCapabilities,EncryptedMediaExtensions,PublicKeyCredential',
      // Hardware acceleration - CRITICAL FOR MOTION SICKNESS
      hardwareAcceleration: true,
      // WebAuthn / Passkeys support
      enableWebAuthn: true,
      // AGGRESSIVE performance optimizations
      enableSmoothScrolling: true,
      backgroundThrottling: false,
      offscreen: false,
      // Force 60fps rendering
      enableLazyLoading: false,
      // Disable problematic features
      enableWebSQL: false,
      // Force hardware acceleration
      webgl: true,
      // Optimize for smooth scrolling
      enableSmoothScrolling: true
    },
    titleBarStyle: 'hiddenInset',
    trafficLightPosition: { x: 20, y: 20 },
    show: false,
    backgroundColor: '#f6f6f6',
    frame: true,
    transparent: false,
    vibrancy: 'light',
    // Keep traffic lights visible even when window is unfocused
    hiddenInsetTitleBarButtonsOnBlur: false,
    // CRITICAL: Force 60fps rendering
    paintWhenInitiallyHidden: false,
    // Disable background throttling
    backgroundThrottling: false
  })

  // Create Extension Manager first (extensions need to be ready for engines)
  extensionManager = new ExtensionManager()
  
  // Create Engine Manager immediately (IPC handlers register in constructor)
  // Pass extensionManager so Tor tabs can load extensions
  engineManager = new EngineManager(mainWindow, extensionManager)
  
  // Create Data Manager for local persistence (only if not already created)
  if (!dataManager) {
    dataManager = new DataManager()
    setupDataIPCHandlers()
  }
  
  // Create Dependency Manager (only if not already created)
  if (!dependencyManager) {
    dependencyManager = new DependencyManager()
    setupDependencyIPCHandlers()
  }

  // Load the native HTML app
  mainWindow.loadFile(path.join(__dirname, 'index.html'))

  // Show window when ready
  mainWindow.once('ready-to-show', () => {
    console.log('🪟 Showing main window...')
    mainWindow.show()
    mainWindow.focus()
  })

  // Also force show after a timeout if ready-to-show doesn't fire
  setTimeout(() => {
    if (mainWindow && !mainWindow.isVisible()) {
      console.log('⚠️ Window not visible after 3s, forcing show...')
      mainWindow.show()
      mainWindow.focus()
    }
  }, 3000)

  // Initialize engines and extensions after window is shown
  mainWindow.webContents.once('did-finish-load', async () => {
    console.log('📄 Window content loaded, checking dependencies...')
    
    try {
      // Check all dependencies first
      const depCheck = await dependencyManager.checkAll()
      
      // Send dependency status to renderer
      mainWindow.webContents.send('dependencies-checked', depCheck)
      
      // Start backend if PHP is available
      if (depCheck.dependencies.php.installed) {
        console.log('🚀 Starting backend server...')
        const backendResult = await dependencyManager.startBackend()
        if (backendResult.success) {
          console.log('✅ Backend server started')
        } else {
          console.warn('⚠️ Backend server failed to start:', backendResult.error)
        }
      } else {
        console.warn('⚠️ PHP not installed - backend server disabled')
        console.log('   Install PHP to enable HavenPay, AI features, and more')
      }
      
      // Initialize extension manager first (loads and auto-installs uBlock Origin)
      await extensionManager.initialize()
      
      // Initialize extension downloader
      extensionDownloader = new ExtensionDownloader(extensionManager)
      
      // Initialize engines (will have access to loaded extensions)
      await engineManager.initialize()
      
      console.log('✅ Prism Browser ready with native engines and extensions')
      
      // Notify renderer that engines are ready
      mainWindow.webContents.send('engines-ready')
    } catch (error) {
      console.error('❌ Initialization error:', error)
      // Still notify renderer so UI can load
      mainWindow.webContents.send('engines-ready')
    }
  })

  // Handle window closed
  mainWindow.on('closed', async () => {
    if (dependencyManager) {
      dependencyManager.stopBackend()
    }
    if (engineManager) {
      await engineManager.shutdown()
      engineManager = null
    }
    if (extensionManager) {
      await extensionManager.shutdown()
      extensionManager = null
    }
    mainWindow = null
  })

  // Create menu
  createMenu()
}

function createMenu() {
  const template = [
    {
      label: 'Prism',
      submenu: [
        {
          label: 'About Prism',
          click: () => {
            // Show about dialog
          }
        },
        { type: 'separator' },
        {
          label: 'Preferences...',
          accelerator: 'CmdOrCtrl+,',
          click: () => {
            // Open settings
            mainWindow.webContents.send('open-settings')
          }
        },
        { type: 'separator' },
        {
          label: 'Quit',
          accelerator: process.platform === 'darwin' ? 'Cmd+Q' : 'Ctrl+Q',
          click: () => {
            app.quit()
          }
        }
      ]
    },
    {
      label: 'File',
      submenu: [
        {
          label: 'New Tab',
          accelerator: 'CmdOrCtrl+T',
          click: () => {
            mainWindow.webContents.send('new-tab')
          }
        },
        {
          label: 'New Window',
          accelerator: 'CmdOrCtrl+N',
          click: () => {
            createWindow()
          }
        },
        { type: 'separator' },
        {
          label: 'Close Tab',
          accelerator: 'CmdOrCtrl+W',
          click: () => {
            mainWindow.webContents.send('close-tab')
          }
        }
      ]
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' },
        { role: 'redo' },
        { type: 'separator' },
        { role: 'cut' },
        { role: 'copy' },
        { role: 'paste' },
        { role: 'selectall' }
      ]
    },
    {
      label: 'View',
      submenu: [
        { role: 'reload' },
        { role: 'forceReload' },
        { role: 'toggleDevTools' },
        { type: 'separator' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { type: 'separator' },
        { role: 'togglefullscreen' }
      ]
    },
    {
      label: 'Navigation',
      submenu: [
        {
          label: 'Back',
          accelerator: 'CmdOrCtrl+Left',
          click: () => {
            mainWindow.webContents.send('navigate-back')
          }
        },
        {
          label: 'Forward',
          accelerator: 'CmdOrCtrl+Right',
          click: () => {
            mainWindow.webContents.send('navigate-forward')
          }
        },
        {
          label: 'Refresh',
          accelerator: 'CmdOrCtrl+R',
          click: () => {
            mainWindow.webContents.send('navigate-refresh')
          }
        },
        { type: 'separator' },
        {
          label: 'Home',
          accelerator: 'CmdOrCtrl+H',
          click: () => {
            mainWindow.webContents.send('navigate-home')
          }
        }
      ]
    },
    {
      label: 'Window',
      submenu: [
        { role: 'minimize' },
        { role: 'close' }
      ]
    },
    {
      label: 'Help',
      submenu: [
        {
          label: 'Learn More',
          click: () => {
            shell.openExternal('https://github.com/prism-browser/prism')
          }
        },
        {
          label: 'Documentation',
          click: () => {
            shell.openExternal('https://docs.prism-browser.com')
          }
        }
      ]
    }
  ]

  const menu = Menu.buildFromTemplate(template)
  Menu.setApplicationMenu(menu)
}

// App event handlers
app.whenReady().then(createWindow)

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
  }
})

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow()
  }
})

// Data Management IPC handlers
function setupDataIPCHandlers() {
  // User Management
  ipcMain.handle('data:createUser', async (event, username, password) => {
    return dataManager.createUser(username, password)
  })

  ipcMain.handle('data:login', async (event, username, password) => {
    return dataManager.loginUser(username, password)
  })

  ipcMain.handle('data:logout', async () => {
    return dataManager.logoutUser()
  })

  ipcMain.handle('data:getCurrentUser', async () => {
    return dataManager.getCurrentUser()
  })

  ipcMain.handle('data:getUsers', async () => {
    return dataManager.getUsers().map(u => ({ username: u.username, lastLogin: u.lastLogin }))
  })

  // Password Vault
  ipcMain.handle('data:savePassword', async (event, domain, username, password) => {
    return dataManager.savePassword(domain, username, password)
  })

  ipcMain.handle('data:getPassword', async (event, domain, username) => {
    return dataManager.getPassword(domain, username)
  })

  ipcMain.handle('data:getAllPasswords', async () => {
    return dataManager.getAllPasswords()
  })

  ipcMain.handle('data:deletePassword', async (event, id) => {
    return dataManager.deletePassword(id)
  })

  // Browsing History
  ipcMain.handle('data:addToHistory', async (event, url, title, engine) => {
    return dataManager.addToHistory(url, title, engine)
  })

  ipcMain.handle('data:getHistory', async (event, limit, searchQuery) => {
    return dataManager.getHistory(limit, searchQuery)
  })

  ipcMain.handle('data:clearHistory', async () => {
    return dataManager.clearHistory()
  })

  // Stats
  ipcMain.handle('data:getStats', async () => {
    return dataManager.getStats()
  })

  console.log('✅ Data IPC handlers registered')
}

// Dependency Management IPC handlers
function setupDependencyIPCHandlers() {
  ipcMain.handle('dependencies:check', async () => {
    return await dependencyManager.checkAll()
  })

  ipcMain.handle('dependencies:getStatus', () => {
    return dependencyManager.getStatus()
  })

  ipcMain.handle('dependencies:getInstructions', (event, dependency) => {
    return dependencyManager.getInstallInstructions(dependency)
  })

  ipcMain.handle('dependencies:openInstallPage', (event, dependency) => {
    dependencyManager.openInstallPage(dependency)
    return { success: true }
  })

  ipcMain.handle('dependencies:startBackend', async () => {
    return await dependencyManager.startBackend()
  })

  ipcMain.handle('dependencies:stopBackend', () => {
    dependencyManager.stopBackend()
    return { success: true }
  })

  console.log('✅ Dependency IPC handlers registered')
}

// IPC handlers
ipcMain.handle('get-app-version', () => {
  return app.getVersion()
})

ipcMain.handle('get-platform', () => {
  return process.platform
})

ipcMain.handle('show-message-box', async (event, options) => {
  const { dialog } = require('electron')
  const result = await dialog.showMessageBox(mainWindow, options)
  return result
})

ipcMain.handle('show-save-dialog', async (event, options) => {
  const { dialog } = require('electron')
  const result = await dialog.showSaveDialog(mainWindow, options)
  return result
})

ipcMain.handle('show-open-dialog', async (event, options) => {
  const { dialog } = require('electron')
  const result = await dialog.showOpenDialog(mainWindow, options)
  return result
})

// Window control handlers
ipcMain.handle('close-window', () => {
  mainWindow.close()
})

ipcMain.handle('minimize-window', () => {
  mainWindow.minimize()
})

ipcMain.handle('maximize-window', () => {
  if (mainWindow.isMaximized()) {
    mainWindow.unmaximize()
  } else {
    mainWindow.maximize()
  }
})

// Extension management handlers
ipcMain.handle('extensions:list', () => {
  return extensionManager ? extensionManager.getExtensions() : []
})

ipcMain.handle('extensions:install', async (event, xpiPath) => {
  if (!extensionManager) throw new Error('Extension manager not initialized')
  return await extensionManager.installExtension(xpiPath)
})

ipcMain.handle('extensions:uninstall', async (event, extensionId) => {
  if (!extensionManager) throw new Error('Extension manager not initialized')
  return await extensionManager.uninstallExtension(extensionId)
})

ipcMain.handle('extensions:enable', async (event, extensionId, sessionName) => {
  if (!extensionManager) throw new Error('Extension manager not initialized')
  return await extensionManager.enableExtension(extensionId, sessionName)
})

ipcMain.handle('extensions:disable', async (event, extensionId, sessionName) => {
  if (!extensionManager) throw new Error('Extension manager not initialized')
  return await extensionManager.disableExtension(extensionId, sessionName)
})

ipcMain.handle('extensions:get', (event, extensionId) => {
  return extensionManager ? extensionManager.getExtension(extensionId) : null
})

ipcMain.handle('extensions:stats', () => {
  return extensionManager ? extensionManager.getStats() : { totalExtensions: 0, extensions: [] }
})

// Extension downloader handlers
ipcMain.handle('extensions:search', async (event, query, limit) => {
  if (!extensionDownloader) throw new Error('Extension downloader not initialized')
  return await extensionDownloader.searchAddons(query, limit)
})

ipcMain.handle('extensions:featured', async (event, limit) => {
  if (!extensionDownloader) throw new Error('Extension downloader not initialized')
  return await extensionDownloader.getFeaturedAddons(limit)
})

ipcMain.handle('extensions:installFromUrl', async (event, url, progressCallback) => {
  if (!extensionDownloader) throw new Error('Extension downloader not initialized')
  
  // Create progress handler
  const onProgress = (progress, downloaded, total) => {
    mainWindow.webContents.send('extension-download-progress', { url, progress, downloaded, total })
  }
  
  return await extensionDownloader.installFromMozilla(url, onProgress)
})

ipcMain.handle('extensions:download', async (event, url) => {
  if (!extensionDownloader) throw new Error('Extension downloader not initialized')
  
  const onProgress = (progress, downloaded, total) => {
    mainWindow.webContents.send('extension-download-progress', { url, progress, downloaded, total })
  }
  
  return await extensionDownloader.downloadExtension(url, onProgress)
})
