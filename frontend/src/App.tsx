import React, { useState, useEffect } from 'react'
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom'
import { BrowserWindow } from './components/BrowserWindow'
import { SettingsPanel } from './components/SettingsPanel'
import { EngineSelector } from './components/EngineSelector'
import { TabManager } from './components/TabManager'
import { AddressBar } from './components/AddressBar'
import { BookmarkBar } from './components/BookmarkBar'
import { useEngine } from './hooks/useEngine'
import { useTabs } from './hooks/useTabs'
import { useSettings } from './hooks/useSettings'

function App() {
  const [showSettings, setShowSettings] = useState(false)
  const { currentEngine, switchEngine, engines } = useEngine()
  const { tabs, createTab, closeTab, activeTab, setActiveTab, navigateTab, loading } = useTabs()
  const { settings, updateSettings } = useSettings()

  useEffect(() => {
    // Create initial tab
    if (tabs.length === 0) {
      createTab('New Tab', 'about:blank')
    }
  }, [])

  return (
    <Router>
      <div className="h-screen flex flex-col bg-arc-bg">
        {/* Title Bar */}
        <div className="h-8 bg-arc-surface border-b border-arc-border flex items-center justify-between px-4">
          <div className="flex items-center space-x-2">
            <div className="w-4 h-4 bg-arc-accent rounded-full"></div>
            <span className="text-sm font-medium text-arc-text">Prism Browser</span>
          </div>
          <div className="flex items-center space-x-2">
            <EngineSelector
              engines={engines}
              currentEngine={currentEngine}
              onEngineChange={switchEngine}
            />
            <button
              onClick={() => setShowSettings(!showSettings)}
              className="p-1 hover:bg-arc-border rounded transition-colors"
            >
              <svg className="w-4 h-4 text-arc-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
          </div>
        </div>

        {/* Address Bar */}
        <AddressBar
          currentUrl={activeTab?.url || ''}
          loading={loading}
          onNavigate={(url) => {
            if (activeTab) {
              navigateTab(activeTab.id, url)
            }
          }}
          onRefresh={() => {
            if (activeTab && activeTab.url !== 'about:blank') {
              navigateTab(activeTab.id, activeTab.url)
            }
          }}
          onHome={() => {
            if (activeTab) {
              navigateTab(activeTab.id, 'about:blank')
            }
          }}
        />

        {/* Bookmark Bar */}
        <BookmarkBar />

        {/* Main Content Area */}
        <div className="flex-1 flex">
          {/* Sidebar */}
          <div className="w-64 sidebar flex flex-col">
            <TabManager
              tabs={tabs}
              activeTab={activeTab}
              onTabSelect={setActiveTab}
              onTabClose={closeTab}
              onNewTab={() => createTab('New Tab', 'about:blank')}
            />
          </div>

          {/* Content Area */}
          <div className="flex-1 content-area">
            <BrowserWindow
              tab={activeTab}
              engine={currentEngine}
            />
          </div>
        </div>

        {/* Settings Panel */}
        {showSettings && (
          <SettingsPanel
            settings={settings}
            onSettingsChange={updateSettings}
            onClose={() => setShowSettings(false)}
          />
        )}
      </div>
    </Router>
  )
}

export default App
