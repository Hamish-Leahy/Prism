import React, { useState, useEffect, useRef } from 'react'
import { SearchEngines } from './components/SearchEngines'
import { RevolutionaryTabs } from './components/RevolutionaryTabs'
import { SmartAddressBar } from './components/SmartAddressBar'
import { WebView } from './components/WebView'
import { Sidebar } from './components/Sidebar'
import { CommandPalette } from './components/CommandPalette'

interface Tab {
  id: string
  title: string
  url: string
  favicon: string
  isActive: boolean
  isPinned: boolean
  groupId?: string
  searchEngine?: string
  canGoBack: boolean
  canGoForward: boolean
  isLoading: boolean
}

interface SearchEngine {
  id: string
  name: string
  url: string
  icon: string
  shortcut: string
  isDefault: boolean
}

function App() {
  const [tabs, setTabs] = useState<Tab[]>([])
  const [activeTabId, setActiveTabId] = useState<string | null>(null)
  const [searchEngines, setSearchEngines] = useState<SearchEngine[]>([
    { id: 'google', name: 'Google', url: 'https://www.google.com/search?q=', icon: '🔍', shortcut: 'g', isDefault: true },
    { id: 'duckduckgo', name: 'DuckDuckGo', url: 'https://duckduckgo.com/?q=', icon: '🦆', shortcut: 'd', isDefault: false },
    { id: 'bing', name: 'Bing', url: 'https://www.bing.com/search?q=', icon: '🔎', shortcut: 'b', isDefault: false },
    { id: 'youtube', name: 'YouTube', url: 'https://www.youtube.com/results?search_query=', icon: '📺', shortcut: 'y', isDefault: false },
    { id: 'github', name: 'GitHub', url: 'https://github.com/search?q=', icon: '🐙', shortcut: 'gh', isDefault: false },
    { id: 'stackoverflow', name: 'Stack Overflow', url: 'https://stackoverflow.com/search?q=', icon: '📚', shortcut: 'so', isDefault: false },
    { id: 'reddit', name: 'Reddit', url: 'https://www.reddit.com/search/?q=', icon: '🤖', shortcut: 'r', isDefault: false },
    { id: 'wikipedia', name: 'Wikipedia', url: 'https://en.wikipedia.org/wiki/Special:Search?search=', icon: '📖', shortcut: 'w', isDefault: false }
  ])
  const [showCommandPalette, setShowCommandPalette] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const [currentSearchEngine, setCurrentSearchEngine] = useState<SearchEngine>(searchEngines[0])
  const webviewRef = useRef<HTMLWebViewElement>(null)

  // Initialize with a new tab
  useEffect(() => {
    if (tabs.length === 0) {
      createNewTab()
    }
  }, [])

  const createNewTab = (url: string = 'about:blank', searchEngine?: string) => {
    const newTab: Tab = {
      id: `tab-${Date.now()}`,
      title: 'New Tab',
      url,
      favicon: '🌐',
      isActive: true,
      isPinned: false,
      searchEngine: searchEngine || currentSearchEngine.id,
      canGoBack: false,
      canGoForward: false,
      isLoading: false
    }

    setTabs(prevTabs => {
      const updatedTabs = prevTabs.map(tab => ({ ...tab, isActive: false }))
      return [...updatedTabs, newTab]
    })
    setActiveTabId(newTab.id)
  }

  const closeTab = (tabId: string) => {
    setTabs(prevTabs => {
      const filteredTabs = prevTabs.filter(tab => tab.id !== tabId)
      if (filteredTabs.length === 0) {
        createNewTab()
        return []
      }
      
      // If closing active tab, activate another tab
      if (tabId === activeTabId) {
        const activeIndex = prevTabs.findIndex(tab => tab.id === tabId)
        const newActiveIndex = activeIndex > 0 ? activeIndex - 1 : 0
        const newActiveTab = filteredTabs[newActiveIndex]
        if (newActiveTab) {
          setActiveTabId(newActiveTab.id)
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
  }

  const navigateTab = (tabId: string, url: string) => {
    setTabs(prevTabs => 
      prevTabs.map(tab => 
        tab.id === tabId 
          ? { ...tab, url, isLoading: true, title: 'Loading...' }
          : tab
      )
    )
  }

  const handleSearch = (query: string, searchEngineId?: string) => {
    const engine = searchEngines.find(e => e.id === (searchEngineId || currentSearchEngine.id)) || currentSearchEngine
    const searchUrl = `${engine.url}${encodeURIComponent(query)}`
    createNewTab(searchUrl, engine.id)
  }

  const activeTab = tabs.find(tab => tab.id === activeTabId)

  return (
    <div className="h-screen flex flex-col bg-black text-white overflow-hidden">
      {/* Revolutionary Tab Bar */}
      <RevolutionaryTabs
        tabs={tabs}
        activeTabId={activeTabId}
        onTabSelect={setActiveTab}
        onTabClose={closeTab}
        onNewTab={() => createNewTab()}
        onTabPin={(tabId) => {
          setTabs(prevTabs => 
            prevTabs.map(tab => 
              tab.id === tabId ? { ...tab, isPinned: !tab.isPinned } : tab
            )
          )
        }}
      />

      {/* Smart Address Bar */}
      <SmartAddressBar
        currentUrl={activeTab?.url || ''}
        searchEngines={searchEngines}
        currentSearchEngine={currentSearchEngine}
        onSearch={handleSearch}
        onNavigate={(url) => {
          if (activeTab) {
            navigateTab(activeTab.id, url)
          }
        }}
        onSearchEngineChange={setCurrentSearchEngine}
        onCommandPalette={() => setShowCommandPalette(true)}
      />

      {/* Main Content Area */}
      <div className="flex-1 flex overflow-hidden">
        {/* Sidebar */}
        {!sidebarCollapsed && (
          <Sidebar
            tabs={tabs}
            searchEngines={searchEngines}
            onTabSelect={setActiveTab}
            onTabClose={closeTab}
            onNewTab={() => createNewTab()}
            onSearch={handleSearch}
          />
        )}

        {/* Web Content */}
        <div className="flex-1 bg-white relative">
          {activeTab && (
            <WebView
              ref={webviewRef}
              tab={activeTab}
              onTitleChange={(title) => {
                setTabs(prevTabs => 
                  prevTabs.map(tab => 
                    tab.id === activeTab.id ? { ...tab, title } : tab
                  )
                )
              }}
              onFaviconChange={(favicon) => {
                setTabs(prevTabs => 
                  prevTabs.map(tab => 
                    tab.id === activeTab.id ? { ...tab, favicon } : tab
                  )
                )
              }}
              onLoadingChange={(isLoading) => {
                setTabs(prevTabs => 
                  prevTabs.map(tab => 
                    tab.id === activeTab.id ? { ...tab, isLoading } : tab
                  )
                )
              }}
            />
          )}
        </div>
      </div>

      {/* Command Palette */}
      {showCommandPalette && (
        <CommandPalette
          onClose={() => setShowCommandPalette(false)}
          onSearch={handleSearch}
          searchEngines={searchEngines}
          tabs={tabs}
          onTabSelect={setActiveTab}
        />
      )}
    </div>
  )
}

export default App
