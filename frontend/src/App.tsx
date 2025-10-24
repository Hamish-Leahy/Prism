import { useState, useEffect, useRef } from 'react'
import './styles/globals.css'
import './styles/components.css'

interface Tab {
  id: string
  title: string
  url: string
  favicon: string
  isActive: boolean
  isPinned: boolean
  isLoading: boolean
}

interface SearchEngine {
  id: string
  name: string
  url: string
  icon: string
  shortcut: string
}

function App() {
  const [tabs, setTabs] = useState<Tab[]>([])
  const [activeTabId, setActiveTabId] = useState<string | null>(null)
  const [showCommandPalette, setShowCommandPalette] = useState(false)
  const [sidebarCollapsed] = useState(false)
  const [currentUrl, setCurrentUrl] = useState('')
  const webviewRef = useRef<HTMLWebViewElement>(null)

  const searchEngines: SearchEngine[] = [
    { id: 'google', name: 'Google', url: 'https://www.google.com/search?q=', icon: '🔍', shortcut: 'g' },
    { id: 'duckduckgo', name: 'DuckDuckGo', url: 'https://duckduckgo.com/?q=', icon: '🦆', shortcut: 'd' },
    { id: 'youtube', name: 'YouTube', url: 'https://www.youtube.com/results?search_query=', icon: '📺', shortcut: 'y' },
    { id: 'github', name: 'GitHub', url: 'https://github.com/search?q=', icon: '🐙', shortcut: 'gh' }
  ]

  // Initialize with a new tab
  useEffect(() => {
    if (tabs.length === 0) {
      createNewTab()
    }
  }, [])

  const createNewTab = (url: string = 'about:blank') => {
    const newTab: Tab = {
      id: `tab-${Date.now()}`,
      title: 'New Tab',
      url,
      favicon: '🌐',
      isActive: true,
      isPinned: false,
      isLoading: false
    }

    setTabs(prevTabs => {
      const updatedTabs = prevTabs.map(tab => ({ ...tab, isActive: false }))
      return [...updatedTabs, newTab]
    })
    setActiveTabId(newTab.id)
    setCurrentUrl(url)
  }

  const closeTab = (tabId: string) => {
    setTabs(prevTabs => {
      const filteredTabs = prevTabs.filter(tab => tab.id !== tabId)
      if (filteredTabs.length === 0) {
        createNewTab()
        return []
      }
      
      if (tabId === activeTabId) {
        const activeIndex = prevTabs.findIndex(tab => tab.id === tabId)
        const newActiveIndex = activeIndex > 0 ? activeIndex - 1 : 0
        const newActiveTab = filteredTabs[newActiveIndex]
        if (newActiveTab) {
          setActiveTabId(newActiveTab.id)
          setCurrentUrl(newActiveTab.url)
        }
      }
      
      return filteredTabs
    })
  }

  const setActiveTab = (tabId: string) => {
    setTabs(prevTabs => 
      prevTabs.map(tab => ({ ...tab, isActive: tab.id === tabId }))
    )
    setActiveTabId(tabId)
    const tab = tabs.find(t => t.id === tabId)
    if (tab) {
      setCurrentUrl(tab.url)
    }
  }

  const navigateToUrl = (url: string) => {
    if (activeTabId) {
      setTabs(prevTabs => 
        prevTabs.map(tab => 
          tab.id === activeTabId 
            ? { ...tab, url, isLoading: true, title: 'Loading...' }
            : tab
        )
      )
      setCurrentUrl(url)
    }
  }

  const handleSearch = (query: string, engineId: string = 'google') => {
    const engine = searchEngines.find(e => e.id === engineId) || searchEngines[0]
    const searchUrl = `${engine.url}${encodeURIComponent(query)}`
    navigateToUrl(searchUrl)
  }

  const activeTab = tabs.find(tab => tab.id === activeTabId)

  return (
    <div className="app">
      {/* Tab Bar */}
      <div className="tab-bar">
        <div className="tabs-container">
          {tabs.map((tab) => (
            <div
              key={tab.id}
              className={`tab ${tab.isActive ? 'active' : ''} ${tab.isPinned ? 'pinned' : ''}`}
              onClick={() => setActiveTab(tab.id)}
            >
              <span className="tab-favicon">{tab.favicon}</span>
              <span className="tab-title">{tab.title}</span>
              {tab.isLoading && <div className="loading-spinner" />}
              <button
                className="tab-close"
                onClick={(e) => {
                  e.stopPropagation()
                  closeTab(tab.id)
                }}
              >
                ×
              </button>
            </div>
          ))}
          <button className="tab-new" onClick={() => createNewTab()}>
            +
          </button>
        </div>
      </div>

      {/* Address Bar */}
      <div className="address-bar">
        <div className="nav-buttons">
          <button className="nav-button" title="Back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <button className="nav-button" title="Forward">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M9 5l7 7-7 7"/>
            </svg>
          </button>
          <button className="nav-button" title="Refresh">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
          </button>
        </div>

        <div className="address-input-container">
          <input
            type="text"
            className="address-input"
            value={currentUrl}
            onChange={(e) => setCurrentUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                navigateToUrl(e.currentTarget.value)
              }
            }}
            placeholder="Search or enter URL..."
          />
        </div>

        <div className="address-actions">
          <button 
            className="nav-button" 
            onClick={() => setShowCommandPalette(true)}
            title="Command Palette (Cmd+Shift+P)"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="main-content">
        {/* Sidebar */}
        {!sidebarCollapsed && (
          <div className="sidebar">
            <div className="sidebar-header">
              <div className="app-logo">
                <div className="logo-icon">🔮</div>
                <div className="logo-text">
                  <h1>Prism</h1>
                  <p>Revolutionary Browser</p>
                </div>
              </div>
            </div>

            <div className="sidebar-nav">
              <button className="sidebar-nav-item active">Tabs</button>
              <button className="sidebar-nav-item">Search</button>
              <button className="sidebar-nav-item">AI</button>
            </div>

            <div className="sidebar-content">
              <div className="tabs-section">
                <h3>Recent Tabs</h3>
                <div className="tab-list">
                  {tabs.map((tab) => (
                    <div
                      key={tab.id}
                      className={`tab-item ${tab.isActive ? 'active' : ''}`}
                      onClick={() => setActiveTab(tab.id)}
                    >
                      <span className="tab-favicon">{tab.favicon}</span>
                      <span className="tab-title">{tab.title}</span>
                      <button
                        className="tab-close"
                        onClick={(e) => {
                          e.stopPropagation()
                          closeTab(tab.id)
                        }}
                      >
                        ×
                      </button>
                    </div>
                  ))}
                </div>
              </div>

              <div className="search-section">
                <h3>Quick Search</h3>
                <div className="search-engines">
                  {searchEngines.map((engine) => (
                    <button
                      key={engine.id}
                      className="search-engine-btn"
                      onClick={() => handleSearch('', engine.id)}
                    >
                      <span className="engine-icon">{engine.icon}</span>
                      <span className="engine-name">{engine.name}</span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Web Content */}
        <div className="web-content">
          {activeTab && (
            <webview
              ref={webviewRef}
              src={activeTab.url}
              style={{ width: '100%', height: '100%' }}
              partition="persist:main"
              allowpopups
              webpreferences="contextIsolation=no, nodeIntegration=yes"
            />
          )}
        </div>
      </div>

      {/* Command Palette */}
      {showCommandPalette && (
        <div className="command-palette">
          <div className="command-palette-content">
            <div className="command-palette-header">
              <h2>Command Palette</h2>
              <input
                type="text"
                className="command-palette-input"
                placeholder="Search commands..."
                autoFocus
                onKeyDown={(e) => {
                  if (e.key === 'Escape') {
                    setShowCommandPalette(false)
                  }
                }}
              />
            </div>
            <div className="command-list">
              <div className="command-group">
                <div className="command-group-title">Navigation</div>
                <div className="command-item">
                  <span className="command-icon">➕</span>
                  <div className="command-content">
                    <div className="command-title">New Tab</div>
                    <div className="command-description">Open a new tab</div>
                  </div>
                  <span className="command-shortcut">Cmd+T</span>
                </div>
                <div className="command-item">
                  <span className="command-icon">❌</span>
                  <div className="command-content">
                    <div className="command-title">Close Tab</div>
                    <div className="command-description">Close current tab</div>
                  </div>
                  <span className="command-shortcut">Cmd+W</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default App
