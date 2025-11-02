import React, { memo, useCallback, useMemo } from 'react'
import { Tab } from '../types/Tab'
import { Plus, X } from 'lucide-react'

interface TabManagerProps {
  tabs: Tab[]
  activeTabId: string | null
  onTabCreate: () => void
  onTabClose: (tabId: string) => void
  onTabSelect: (tabId: string) => void
  onTabUpdate: (tabId: string, updates: Partial<Tab>) => void
}

interface TabItemProps {
  tab: Tab
  isActive: boolean
  onSelect: () => void
  onClose: () => void
}

const TabItem = memo<TabItemProps>(({ tab, isActive, onSelect, onClose }) => {
  const handleClose = useCallback((e: React.MouseEvent) => {
    e.stopPropagation()
    onClose()
  }, [onClose])

  return (
    <div
      className={`group flex items-center gap-2 px-4 py-2.5 rounded-t-xl cursor-pointer transition-all duration-200 transform-gpu ${
        isActive
          ? 'bg-white dark:bg-gray-900 border-t-2 border-blue-600 shadow-sm z-10 scale-[1.02]'
          : 'bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700'
      }`}
      onClick={onSelect}
      style={{
        willChange: 'transform',
        backfaceVisibility: 'hidden',
        transform: 'translateZ(0)'
      }}
    >
      {tab.favicon && (
        <span className="text-xs flex-shrink-0" style={{ fontSize: '14px' }}>
          {tab.favicon}
        </span>
      )}
      <span 
        className={`text-sm font-medium truncate max-w-[200px] ${
          isActive 
            ? 'text-gray-900 dark:text-white' 
            : 'text-gray-600 dark:text-gray-300'
        }`}
        title={tab.title || tab.url}
      >
        {tab.title || 'New Tab'}
      </span>
      {tab.isLoading && (
        <div className="w-3 h-3 border-2 border-blue-600 border-t-transparent rounded-full animate-spin flex-shrink-0" />
      )}
      <button
        onClick={handleClose}
        className={`p-0.5 rounded-md transition-colors flex-shrink-0 opacity-0 group-hover:opacity-100 ${
          isActive 
            ? 'hover:bg-gray-200 dark:hover:bg-gray-700' 
            : 'hover:bg-gray-300 dark:hover:bg-gray-600'
        }`}
        aria-label="Close tab"
      >
        <X className="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
      </button>
    </div>
  )
})

TabItem.displayName = 'TabItem'

export const TabManager = memo<TabManagerProps>(({
  tabs,
  activeTabId,
  onTabCreate,
  onTabClose,
  onTabSelect
}) => {
  const handleTabSelect = useCallback((tabId: string) => {
    onTabSelect(tabId)
  }, [onTabSelect])

  const handleTabClose = useCallback((tabId: string) => {
    onTabClose(tabId)
  }, [onTabClose])

  const sortedTabs = useMemo(() => {
    // Sort tabs: pinned first, then by creation time
    return [...tabs].sort((a, b) => {
      if (a.isPinned && !b.isPinned) return -1
      if (!a.isPinned && b.isPinned) return 1
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
    })
  }, [tabs])

  return (
    <div 
      className="h-14 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 flex items-end px-3 overflow-x-auto scrollbar-hide"
      style={{
        scrollbarWidth: 'none',
        msOverflowStyle: 'none',
        willChange: 'scroll-position',
        transform: 'translateZ(0)'
      }}
    >
      <div className="flex items-end gap-1 min-w-full flex-nowrap">
        {sortedTabs.map((tab) => (
          <TabItem
            key={tab.id}
            tab={tab}
            isActive={activeTabId === tab.id}
            onSelect={() => handleTabSelect(tab.id)}
            onClose={() => handleTabClose(tab.id)}
          />
        ))}
        
        <button
          onClick={onTabCreate}
          className="mb-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors flex-shrink-0"
          title="New Tab (Cmd+T)"
          aria-label="New Tab"
        >
          <Plus className="w-5 h-5 text-gray-600 dark:text-gray-400" />
        </button>
      </div>
      
      <style>{`
        .scrollbar-hide::-webkit-scrollbar {
          display: none;
        }
      `}</style>
    </div>
  )
})

TabManager.displayName = 'TabManager'