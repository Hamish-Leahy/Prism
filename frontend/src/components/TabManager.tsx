import React from 'react'
import { Tab } from '../types/Tab'
import { Plus, X } from 'lucide-react'

interface TabManagerProps {
  tabs: Tab[]
  activeTab: Tab | null
  onTabSelect: (tab: Tab) => void
  onTabClose: (tabId: string) => void
  onNewTab: () => void
}

export const TabManager: React.FC<TabManagerProps> = ({
  tabs,
  activeTab,
  onTabSelect,
  onTabClose,
  onNewTab
}) => {
  return (
    <div className="h-full flex flex-col">
      {/* Tab List */}
      <div className="flex-1 overflow-y-auto">
        <div className="p-4">
          <div className="text-xs font-medium text-arc-text-secondary uppercase tracking-wide mb-3">
            Open Tabs ({tabs.length})
          </div>
          <div className="space-y-1">
            {tabs.map((tab) => (
              <div
                key={tab.id}
                onClick={() => onTabSelect(tab)}
                className={`group flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors ${
                  activeTab?.id === tab.id
                    ? 'bg-arc-accent text-white'
                    : 'bg-arc-surface hover:bg-arc-border text-arc-text'
                }`}
              >
                <div className="flex-1 min-w-0">
                  <div className="font-medium text-sm truncate">
                    {tab.title}
                  </div>
                  <div className={`text-xs truncate ${
                    activeTab?.id === tab.id
                      ? 'text-blue-100'
                      : 'text-arc-text-secondary'
                  }`}>
                    {tab.url}
                  </div>
                </div>
                <button
                  onClick={(e) => {
                    e.stopPropagation()
                    onTabClose(tab.id)
                  }}
                  className={`ml-2 p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors ${
                    activeTab?.id === tab.id
                      ? 'text-blue-100 hover:text-white'
                      : 'text-arc-text-secondary hover:text-arc-text'
                  }`}
                >
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* New Tab Button */}
      <div className="p-4 border-t border-arc-border">
        <button
          onClick={onNewTab}
          className="w-full flex items-center justify-center space-x-2 py-3 bg-arc-surface border border-arc-border rounded-lg hover:bg-arc-border transition-colors"
        >
          <Plus className="w-4 h-4 text-arc-text-secondary" />
          <span className="text-sm font-medium text-arc-text">New Tab</span>
        </button>
      </div>

      {/* Quick Actions */}
      <div className="p-4 border-t border-arc-border">
        <div className="text-xs font-medium text-arc-text-secondary uppercase tracking-wide mb-3">
          Quick Actions
        </div>
        <div className="space-y-2">
          <button className="w-full text-left px-3 py-2 text-sm text-arc-text-secondary hover:text-arc-text hover:bg-arc-border rounded transition-colors">
            Bookmarks
          </button>
          <button className="w-full text-left px-3 py-2 text-sm text-arc-text-secondary hover:text-arc-text hover:bg-arc-border rounded transition-colors">
            History
          </button>
          <button className="w-full text-left px-3 py-2 text-sm text-arc-text-secondary hover:text-arc-text hover:bg-arc-border rounded transition-colors">
            Downloads
          </button>
        </div>
      </div>
    </div>
  )
}
