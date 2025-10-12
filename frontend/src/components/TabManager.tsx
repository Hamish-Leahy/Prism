import React, { useState } from 'react'
import { Tab, TabGroup } from '../types/Tab'
import { Plus, X, Search, FolderPlus, Pin, Copy, MoreHorizontal } from 'lucide-react'
import { TabGroup as TabGroupComponent } from './TabGroup'
import { TabSearch } from './TabSearch'
import { GroupCreator } from './GroupCreator'

interface TabManagerProps {
  tabs: Tab[]
  tabGroups: TabGroup[]
  activeTab: Tab | null
  searchQuery: string
  onTabSelect: (tab: Tab) => void
  onTabClose: (tabId: string) => void
  onTabPin: (tabId: string) => void
  onTabUnpin: (tabId: string) => void
  onTabDuplicate: (tabId: string) => void
  onNewTab: (groupId?: string) => void
  onSearchChange: (query: string) => void
  onCreateGroup: (name: string, color: string, icon?: string) => void
  onGroupUpdate: (groupId: string, updates: Partial<TabGroup>) => void
  onGroupDelete: (groupId: string) => void
  onTabRemoveFromGroup: (tabId: string) => void
  getTabsByGroup: (groupId?: string) => Tab[]
  getUngroupedTabs: () => Tab[]
}

export const TabManager: React.FC<TabManagerProps> = ({
  tabs,
  tabGroups,
  activeTab,
  searchQuery,
  onTabSelect,
  onTabClose,
  onTabPin,
  onTabUnpin,
  onTabDuplicate,
  onNewTab,
  onSearchChange,
  onCreateGroup,
  onGroupUpdate,
  onGroupDelete,
  onTabRemoveFromGroup,
  getTabsByGroup,
  getUngroupedTabs
}) => {
  const [showGroupCreator, setShowGroupCreator] = useState(false)
  const [showSearch, setShowSearch] = useState(false)
  const ungroupedTabs = getUngroupedTabs()
  const pinnedTabs = ungroupedTabs.filter(tab => tab.isPinned)
  const unpinnedTabs = ungroupedTabs.filter(tab => !tab.isPinned)

  return (
    <div className="h-full flex flex-col">
      {/* Search Bar */}
      {showSearch && (
        <TabSearch
          searchQuery={searchQuery}
          onSearchChange={onSearchChange}
          resultCount={tabs.length}
        />
      )}

      {/* Tab List */}
      <div className="flex-1 overflow-y-auto">
        <div className="p-4">
          <div className="flex items-center justify-between mb-3">
            <div className="text-xs font-medium text-arc-text-secondary uppercase tracking-wide">
              Open Tabs ({tabs.length})
            </div>
            <div className="flex items-center space-x-1">
              <button
                onClick={() => setShowSearch(!showSearch)}
                className={`p-1 rounded transition-colors ${
                  showSearch 
                    ? 'bg-arc-accent text-white' 
                    : 'hover:bg-arc-border text-arc-text-secondary'
                }`}
                title="Search tabs"
              >
                <Search className="w-4 h-4" />
              </button>
              <button
                onClick={() => setShowGroupCreator(true)}
                className="p-1 hover:bg-arc-border rounded transition-colors text-arc-text-secondary"
                title="Create group"
              >
                <FolderPlus className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Tab Groups */}
          {tabGroups.map((group) => (
            <TabGroupComponent
              key={group.id}
              group={group}
              tabs={getTabsByGroup(group.id)}
              activeTab={activeTab}
              onTabSelect={onTabSelect}
              onTabClose={onTabClose}
              onTabPin={onTabPin}
              onTabUnpin={onTabUnpin}
              onTabDuplicate={onTabDuplicate}
              onGroupUpdate={onGroupUpdate}
              onGroupDelete={onGroupDelete}
              onTabRemoveFromGroup={onTabRemoveFromGroup}
              onNewTab={onNewTab}
            />
          ))}

          {/* Ungrouped Tabs */}
          {ungroupedTabs.length > 0 && (
            <div className="mb-4">
              <div className="text-xs font-medium text-arc-text-secondary uppercase tracking-wide mb-2">
                Ungrouped Tabs
              </div>
              
              {/* Pinned Ungrouped Tabs */}
              {pinnedTabs.length > 0 && (
                <div className="space-y-1 mb-2">
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

              {/* Unpinned Ungrouped Tabs */}
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
      </div>

      {/* New Tab Button */}
      <div className="p-4 border-t border-arc-border">
        <button
          onClick={() => onNewTab()}
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

      {/* Group Creator Modal */}
      {showGroupCreator && (
        <GroupCreator
          onCreateGroup={onCreateGroup}
          onClose={() => setShowGroupCreator(false)}
        />
      )}
    </div>
  )
}
