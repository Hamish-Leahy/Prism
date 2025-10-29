import React from 'react'
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

export const TabManager: React.FC<TabManagerProps> = ({
  tabs,
  activeTabId,
  onTabCreate,
  onTabClose,
  onTabSelect
}) => {
  return (
    <div className="h-12 bg-arc-surface border-b border-arc-border flex items-center px-4 space-x-2 overflow-x-auto">
      {tabs.map((tab) => (
        <div
          key={tab.id}
          className={`flex items-center space-x-2 px-3 py-2 rounded-t-lg cursor-pointer transition-colors ${
            activeTabId === tab.id
              ? 'bg-arc-bg border-t-2 border-arc-accent'
              : 'bg-arc-surface hover:bg-arc-border'
          }`}
          onClick={() => onTabSelect(tab.id)}
        >
          <span className="text-sm text-arc-text truncate max-w-32">
            {tab.title || 'New Tab'}
          </span>
          <button
            onClick={(e) => {
              e.stopPropagation()
              onTabClose(tab.id)
            }}
            className="p-1 hover:bg-arc-border rounded transition-colors"
          >
            <X className="w-3 h-3 text-arc-text-secondary" />
          </button>
        </div>
      ))}
      
      <button
        onClick={onTabCreate}
        className="p-2 hover:bg-arc-border rounded transition-colors"
        title="New Tab"
      >
        <Plus className="w-4 h-4 text-arc-text-secondary" />
      </button>
    </div>
  )
}