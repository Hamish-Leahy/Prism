import React, { useState } from 'react'
import { TabGroup as TabGroupType, Tab } from '../types/Tab'
import { ChevronDown, ChevronRight, MoreHorizontal, Plus, X, Pin, Copy, Trash2 } from 'lucide-react'

interface TabGroupProps {
  group: TabGroupType
  tabs: Tab[]
  activeTab: Tab | null
  onTabSelect: (tab: Tab) => void
  onTabClose: (tabId: string) => void
  onTabPin: (tabId: string) => void
  onTabUnpin: (tabId: string) => void
  onTabDuplicate: (tabId: string) => void
  onGroupUpdate: (groupId: string, updates: Partial<TabGroupType>) => void
  onGroupDelete: (groupId: string) => void
  onTabRemoveFromGroup: (tabId: string) => void
  onNewTab: (groupId?: string) => void
}

export const TabGroup: React.FC<TabGroupProps> = ({
  group,
  tabs,
  activeTab,
  onTabSelect,
  onTabClose,
  onTabPin,
  onTabUnpin,
  onTabDuplicate,
  onGroupUpdate,
  onGroupDelete,
  onTabRemoveFromGroup,
  onNewTab
}) => {
  const [showContextMenu, setShowContextMenu] = useState(false)
  const [isEditing, setIsEditing] = useState(false)
  const [editName, setEditName] = useState(group.name)

  const groupTabs = tabs.filter(tab => tab.groupId === group.id)
  const pinnedTabs = groupTabs.filter(tab => tab.isPinned)
  const unpinnedTabs = groupTabs.filter(tab => !tab.isPinned)

  const handleNameEdit = () => {
    if (editName.trim()) {
      onGroupUpdate(group.id, { name: editName.trim() })
    }
    setIsEditing(false)
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleNameEdit()
    } else if (e.key === 'Escape') {
      setEditName(group.name)
      setIsEditing(false)
    }
  }

  return (
    <div className="mb-4">
      {/* Group Header */}
      <div className="flex items-center justify-between p-2 bg-arc-surface rounded-lg mb-2">
        <div className="flex items-center space-x-2 flex-1">
          <button
            onClick={() => onGroupUpdate(group.id, { isCollapsed: !group.isCollapsed })}
            className="p-1 hover:bg-arc-border rounded transition-colors"
          >
            {group.isCollapsed ? (
              <ChevronRight className="w-4 h-4 text-arc-text-secondary" />
            ) : (
              <ChevronDown className="w-4 h-4 text-arc-text-secondary" />
            )}
          </button>
          
          <div 
            className="w-3 h-3 rounded-full"
            style={{ backgroundColor: group.color }}
          />
          
          {isEditing ? (
            <input
              type="text"
              value={editName}
              onChange={(e) => setEditName(e.target.value)}
              onBlur={handleNameEdit}
              onKeyDown={handleKeyPress}
              className="bg-transparent border-none outline-none text-sm font-medium text-arc-text flex-1"
              autoFocus
            />
          ) : (
            <span 
              className="text-sm font-medium text-arc-text flex-1 cursor-pointer"
              onClick={() => setIsEditing(true)}
            >
              {group.name} ({groupTabs.length})
            </span>
          )}
        </div>

        <div className="flex items-center space-x-1">
          <button
            onClick={() => onNewTab(group.id)}
            className="p-1 hover:bg-arc-border rounded transition-colors"
            title="New tab in group"
          >
            <Plus className="w-4 h-4 text-arc-text-secondary" />
          </button>
          
          <div className="relative">
            <button
              onClick={() => setShowContextMenu(!showContextMenu)}
              className="p-1 hover:bg-arc-border rounded transition-colors"
            >
              <MoreHorizontal className="w-4 h-4 text-arc-text-secondary" />
            </button>
            
            {showContextMenu && (
              <div className="absolute right-0 top-8 bg-arc-surface border border-arc-border rounded-lg shadow-lg z-10 min-w-48">
                <button
                  onClick={() => {
                    setIsEditing(true)
                    setShowContextMenu(false)
                  }}
                  className="w-full text-left px-3 py-2 text-sm text-arc-text hover:bg-arc-border transition-colors"
                >
                  Rename group
                </button>
                <button
                  onClick={() => {
                    onGroupUpdate(group.id, { isCollapsed: !group.isCollapsed })
                    setShowContextMenu(false)
                  }}
                  className="w-full text-left px-3 py-2 text-sm text-arc-text hover:bg-arc-border transition-colors"
                >
                  {group.isCollapsed ? 'Expand' : 'Collapse'} group
                </button>
                <button
                  onClick={() => {
                    onGroupDelete(group.id)
                    setShowContextMenu(false)
                  }}
                  className="w-full text-left px-3 py-2 text-sm text-arc-error hover:bg-arc-border transition-colors"
                >
                  Delete group
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Group Tabs */}
      {!group.isCollapsed && (
        <div className="ml-4 space-y-1">
          {/* Pinned Tabs */}
          {pinnedTabs.length > 0 && (
            <div className="space-y-1">
              {pinnedTabs.map((tab) => (
                <div
                  key={tab.id}
                  className={`group flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors ${
                    activeTab?.id === tab.id
                      ? 'bg-arc-accent text-white'
                      : 'bg-arc-surface hover:bg-arc-border text-arc-text'
                  }`}
                >
                  <div 
                    className="flex-1 min-w-0 flex items-center space-x-2"
                    onClick={() => onTabSelect(tab)}
                  >
                    <Pin className="w-3 h-3 text-arc-text-secondary flex-shrink-0" />
                    <div className="min-w-0 flex-1">
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
                  </div>
                  
                  <div className="flex items-center space-x-1">
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabUnpin(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Unpin tab"
                    >
                      <Pin className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabDuplicate(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Duplicate tab"
                    >
                      <Copy className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabRemoveFromGroup(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Remove from group"
                    >
                      <X className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabClose(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Unpinned Tabs */}
          {unpinnedTabs.length > 0 && (
            <div className="space-y-1">
              {unpinnedTabs.map((tab) => (
                <div
                  key={tab.id}
                  className={`group flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors ${
                    activeTab?.id === tab.id
                      ? 'bg-arc-accent text-white'
                      : 'bg-arc-surface hover:bg-arc-border text-arc-text'
                  }`}
                >
                  <div 
                    className="flex-1 min-w-0 flex items-center space-x-2"
                    onClick={() => onTabSelect(tab)}
                  >
                    <div className="w-3 h-3 flex-shrink-0" />
                    <div className="min-w-0 flex-1">
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
                  </div>
                  
                  <div className="flex items-center space-x-1">
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabPin(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Pin tab"
                    >
                      <Pin className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabDuplicate(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Duplicate tab"
                    >
                      <Copy className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabRemoveFromGroup(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                      title="Remove from group"
                    >
                      <X className="w-3 h-3" />
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        onTabClose(tab.id)
                      }}
                      className="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
