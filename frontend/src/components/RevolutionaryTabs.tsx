import React, { useState, useRef, useEffect } from 'react'

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

interface RevolutionaryTabsProps {
  tabs: Tab[]
  activeTabId: string | null
  onTabSelect: (tabId: string) => void
  onTabClose: (tabId: string) => void
  onNewTab: () => void
  onTabPin: (tabId: string) => void
}

export function RevolutionaryTabs({
  tabs,
  activeTabId,
  onTabSelect,
  onTabClose,
  onNewTab,
  onTabPin
}: RevolutionaryTabsProps) {
  const [draggedTab, setDraggedTab] = useState<string | null>(null)
  const [tabGroups, setTabGroups] = useState<{[key: string]: string[]}>({})
  const tabRefs = useRef<{[key: string]: HTMLDivElement}>({})

  const pinnedTabs = tabs.filter(tab => tab.isPinned)
  const unpinnedTabs = tabs.filter(tab => !tab.isPinned)

  const handleDragStart = (e: React.DragEvent, tabId: string) => {
    setDraggedTab(tabId)
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
  }

  const handleDrop = (e: React.DragEvent, targetTabId: string) => {
    e.preventDefault()
    if (draggedTab && draggedTab !== targetTabId) {
      // Reorder tabs logic here
      console.log(`Moving tab ${draggedTab} to position of ${targetTabId}`)
    }
    setDraggedTab(null)
  }

  const handleTabDoubleClick = (tabId: string) => {
    onTabPin(tabId)
  }

  return (
    <div className="h-16 bg-gray-900 border-b border-gray-700 flex items-center px-2 space-x-1 overflow-x-auto">
      {/* Pinned Tabs */}
      {pinnedTabs.map((tab) => (
        <div
          key={tab.id}
          ref={(el) => { if (el) tabRefs.current[tab.id] = el }}
          draggable
          onDragStart={(e) => handleDragStart(e, tab.id)}
          onDragOver={handleDragOver}
          onDrop={(e) => handleDrop(e, tab.id)}
          className={`
            flex items-center space-x-2 px-3 py-2 rounded-lg cursor-pointer transition-all duration-200 min-w-0 group relative
            ${tab.isActive 
              ? 'bg-blue-600 text-white shadow-lg transform scale-105' 
              : 'hover:bg-gray-700 text-gray-300 hover:text-white'
            }
            ${tab.isPinned ? 'border-l-2 border-yellow-400' : ''}
          `}
          onClick={() => onTabSelect(tab.id)}
          onDoubleClick={() => handleTabDoubleClick(tab.id)}
        >
          {/* Favicon */}
          <div className="w-4 h-4 flex-shrink-0">
            {tab.isLoading ? (
              <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
            ) : (
              <span className="text-sm">{tab.favicon}</span>
            )}
          </div>

          {/* Tab Title */}
          <span className="text-sm truncate max-w-32 font-medium">
            {tab.title}
          </span>

          {/* Tab Actions */}
          <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              onClick={(e) => {
                e.stopPropagation()
                onTabPin(tab.id)
              }}
              className="p-1 hover:bg-gray-600 rounded transition-colors"
              title={tab.isPinned ? 'Unpin tab' : 'Pin tab'}
            >
              <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
            </button>
            <button
              onClick={(e) => {
                e.stopPropagation()
                onTabClose(tab.id)
              }}
              className="p-1 hover:bg-red-600 rounded transition-colors"
            >
              <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Loading Indicator */}
          {tab.isLoading && (
            <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-400 animate-pulse" />
          )}
        </div>
      ))}

      {/* Separator */}
      {pinnedTabs.length > 0 && unpinnedTabs.length > 0 && (
        <div className="w-px h-8 bg-gray-600 mx-2" />
      )}

      {/* Unpinned Tabs */}
      {unpinnedTabs.map((tab) => (
        <div
          key={tab.id}
          ref={(el) => { if (el) tabRefs.current[tab.id] = el }}
          draggable
          onDragStart={(e) => handleDragStart(e, tab.id)}
          onDragOver={handleDragOver}
          onDrop={(e) => handleDrop(e, tab.id)}
          className={`
            flex items-center space-x-2 px-3 py-2 rounded-lg cursor-pointer transition-all duration-200 min-w-0 group relative
            ${tab.isActive 
              ? 'bg-blue-600 text-white shadow-lg transform scale-105' 
              : 'hover:bg-gray-700 text-gray-300 hover:text-white'
            }
          `}
          onClick={() => onTabSelect(tab.id)}
          onDoubleClick={() => handleTabDoubleClick(tab.id)}
        >
          {/* Favicon */}
          <div className="w-4 h-4 flex-shrink-0">
            {tab.isLoading ? (
              <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
            ) : (
              <span className="text-sm">{tab.favicon}</span>
            )}
          </div>

          {/* Tab Title */}
          <span className="text-sm truncate max-w-32 font-medium">
            {tab.title}
          </span>

          {/* Tab Actions */}
          <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              onClick={(e) => {
                e.stopPropagation()
                onTabPin(tab.id)
              }}
              className="p-1 hover:bg-gray-600 rounded transition-colors"
              title="Pin tab"
            >
              <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
            </button>
            <button
              onClick={(e) => {
                e.stopPropagation()
                onTabClose(tab.id)
              }}
              className="p-1 hover:bg-red-600 rounded transition-colors"
            >
              <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Loading Indicator */}
          {tab.isLoading && (
            <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-400 animate-pulse" />
          )}
        </div>
      ))}

      {/* New Tab Button */}
      <button
        onClick={onNewTab}
        className="p-2 hover:bg-gray-700 rounded-lg transition-colors text-gray-400 hover:text-white"
        title="New Tab (Cmd+T)"
      >
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
      </button>

      {/* Tab Overflow Indicator */}
      {tabs.length > 10 && (
        <div className="px-2 text-gray-500 text-sm">
          +{tabs.length - 10} more
        </div>
      )}
    </div>
  )
}
