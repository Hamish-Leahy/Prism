import React, { useState } from 'react'

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

interface SidebarProps {
  tabs: Tab[]
  searchEngines: SearchEngine[]
  onTabSelect: (tabId: string) => void
  onTabClose: (tabId: string) => void
  onNewTab: () => void
  onSearch: (query: string, searchEngineId?: string) => void
}

export function Sidebar({
  tabs,
  searchEngines,
  onTabSelect,
  onTabClose,
  onNewTab,
  onSearch
}: SidebarProps) {
  const [activeSection, setActiveSection] = useState('tabs')
  const [searchQuery, setSearchQuery] = useState('')

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      onSearch(searchQuery.trim())
      setSearchQuery('')
    }
  }

  const pinnedTabs = tabs.filter(tab => tab.isPinned)
  const recentTabs = tabs.filter(tab => !tab.isPinned).slice(0, 10)

  return (
    <div className="w-80 bg-gray-900 border-r border-gray-700 flex flex-col h-full">
      {/* Sidebar Header */}
      <div className="p-4 border-b border-gray-700">
        <div className="flex items-center space-x-3">
          <div className="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
            <span className="text-white font-bold text-lg">P</span>
          </div>
          <div>
            <h1 className="text-white font-semibold">Prism Browser</h1>
            <p className="text-gray-400 text-sm">Revolutionary Browsing</p>
          </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex border-b border-gray-700">
        <button
          onClick={() => setActiveSection('tabs')}
          className={`flex-1 py-3 px-4 text-sm font-medium transition-colors ${
            activeSection === 'tabs'
              ? 'text-blue-400 border-b-2 border-blue-400'
              : 'text-gray-400 hover:text-white'
          }`}
        >
          Tabs
        </button>
        <button
          onClick={() => setActiveSection('search')}
          className={`flex-1 py-3 px-4 text-sm font-medium transition-colors ${
            activeSection === 'search'
              ? 'text-blue-400 border-b-2 border-blue-400'
              : 'text-gray-400 hover:text-white'
          }`}
        >
          Search
        </button>
        <button
          onClick={() => setActiveSection('ai')}
          className={`flex-1 py-3 px-4 text-sm font-medium transition-colors ${
            activeSection === 'ai'
              ? 'text-blue-400 border-b-2 border-blue-400'
              : 'text-gray-400 hover:text-white'
          }`}
        >
          AI
        </button>
      </div>

      {/* Content Area */}
      <div className="flex-1 overflow-y-auto">
        {activeSection === 'tabs' && (
          <div className="p-4 space-y-4">
            {/* Pinned Tabs */}
            {pinnedTabs.length > 0 && (
              <div>
                <h3 className="text-gray-400 text-xs uppercase tracking-wider mb-2">Pinned</h3>
                <div className="space-y-1">
                  {pinnedTabs.map((tab) => (
                    <div
                      key={tab.id}
                      className={`flex items-center space-x-3 p-2 rounded-lg cursor-pointer transition-colors group ${
                        tab.isActive
                          ? 'bg-blue-600 text-white'
                          : 'hover:bg-gray-800 text-gray-300'
                      }`}
                      onClick={() => onTabSelect(tab.id)}
                    >
                      <span className="text-sm">{tab.favicon}</span>
                      <span className="text-sm truncate flex-1">{tab.title}</span>
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          onTabClose(tab.id)
                        }}
                        className="opacity-0 group-hover:opacity-100 p-1 hover:bg-red-600 rounded transition-all"
                      >
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Recent Tabs */}
            <div>
              <h3 className="text-gray-400 text-xs uppercase tracking-wider mb-2">Recent</h3>
              <div className="space-y-1">
                {recentTabs.map((tab) => (
                  <div
                    key={tab.id}
                    className={`flex items-center space-x-3 p-2 rounded-lg cursor-pointer transition-colors group ${
                      tab.isActive
                        ? 'bg-blue-600 text-white'
                        : 'hover:bg-gray-800 text-gray-300'
                    }`}
                    onClick={() => onTabSelect(tab.id)}
                  >
                    <span className="text-sm">{tab.favicon}</span>
                    <span className="text-sm truncate flex-1">{tab.title}</span>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabClose(tab.id)
                      }}
                      className="opacity-0 group-hover:opacity-100 p-1 hover:bg-red-600 rounded transition-all"
                    >
                      <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {/* New Tab Button */}
            <button
              onClick={onNewTab}
              className="w-full p-3 border-2 border-dashed border-gray-600 rounded-lg text-gray-400 hover:border-blue-500 hover:text-blue-400 transition-colors flex items-center justify-center space-x-2"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              <span>New Tab</span>
            </button>
          </div>
        )}

        {activeSection === 'search' && (
          <div className="p-4 space-y-4">
            {/* Quick Search */}
            <form onSubmit={handleSearch} className="space-y-3">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search the web..."
                className="w-full p-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="submit"
                className="w-full p-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                Search
              </button>
            </form>

            {/* Search Engines */}
            <div>
              <h3 className="text-gray-400 text-xs uppercase tracking-wider mb-3">Search Engines</h3>
              <div className="grid grid-cols-2 gap-2">
                {searchEngines.map((engine) => (
                  <button
                    key={engine.id}
                    onClick={() => onSearch('', engine.id)}
                    className="p-3 bg-gray-800 rounded-lg text-gray-300 hover:bg-gray-700 transition-colors text-center"
                  >
                    <div className="text-2xl mb-1">{engine.icon}</div>
                    <div className="text-xs">{engine.name}</div>
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}

        {activeSection === 'ai' && (
          <div className="p-4 space-y-4">
            <div className="text-center">
              <div className="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl">🤖</span>
              </div>
              <h3 className="text-white font-semibold mb-2">AI Assistant</h3>
              <p className="text-gray-400 text-sm mb-4">
                Get intelligent help with your browsing
              </p>
            </div>

            <div className="space-y-3">
              <button className="w-full p-3 bg-gray-800 rounded-lg text-left text-gray-300 hover:bg-gray-700 transition-colors">
                <div className="flex items-center space-x-3">
                  <span className="text-lg">🔍</span>
                  <div>
                    <div className="font-medium">Smart Search</div>
                    <div className="text-xs text-gray-400">AI-powered search suggestions</div>
                  </div>
                </div>
              </button>

              <button className="w-full p-3 bg-gray-800 rounded-lg text-left text-gray-300 hover:bg-gray-700 transition-colors">
                <div className="flex items-center space-x-3">
                  <span className="text-lg">📝</span>
                  <div>
                    <div className="font-medium">Summarize Page</div>
                    <div className="text-xs text-gray-400">Get AI summary of current page</div>
                  </div>
                </div>
              </button>

              <button className="w-full p-3 bg-gray-800 rounded-lg text-left text-gray-300 hover:bg-gray-700 transition-colors">
                <div className="flex items-center space-x-3">
                  <span className="text-lg">🌐</span>
                  <div>
                    <div className="font-medium">Translate</div>
                    <div className="text-xs text-gray-400">Translate current page</div>
                  </div>
                </div>
              </button>

              <button className="w-full p-3 bg-gray-800 rounded-lg text-left text-gray-300 hover:bg-gray-700 transition-colors">
                <div className="flex items-center space-x-3">
                  <span className="text-lg">⚡</span>
                  <div>
                    <div className="font-medium">Performance</div>
                    <div className="text-xs text-gray-400">Optimize page performance</div>
                  </div>
                </div>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
