import { useState, useEffect } from 'react'
import { Tab, TabGroup } from '../types/Tab'
import { apiService } from '../services/api'

export const useTabs = () => {
  const [tabs, setTabs] = useState<Tab[]>([])
  const [tabGroups, setTabGroups] = useState<TabGroup[]>([])
  const [activeTab, setActiveTab] = useState<Tab | null>(null)
  const [loading, setLoading] = useState<boolean>(false)
  const [searchQuery, setSearchQuery] = useState<string>('')

  useEffect(() => {
    loadTabs()
  }, [])

  const loadTabs = async () => {
    try {
      const response = await apiService.getTabs()
      if (response.success && response.data) {
        setTabs(response.data)
        
        // Set first tab as active if none is active
        if (response.data.length > 0 && !activeTab) {
          const firstActive = response.data.find((tab: Tab) => tab.isActive)
          setActiveTab(firstActive || response.data[0])
        }
      } else {
        console.error('Failed to load tabs:', response.error)
      }
    } catch (error) {
      console.error('Failed to load tabs:', error)
    }
  }

  const createTab = async (title: string, url: string, groupId?: string) => {
    try {
      const response = await apiService.createTab(title, url)
      if (response.success && response.data) {
        const newTab = { 
          ...response.data, 
          isActive: true,
          groupId: groupId,
          isPinned: false,
          isLoading: false,
          canGoBack: false,
          canGoForward: false
        }
        
        // Deactivate other tabs
        setTabs((prevTabs: Tab[]) => 
          prevTabs.map((tab: Tab) => ({ ...tab, isActive: false }))
        )
        
        // Add new tab
        setTabs((prevTabs: Tab[]) => [...prevTabs, newTab])
        setActiveTab(newTab)
      } else {
        console.error('Failed to create tab:', response.error)
      }
    } catch (error) {
      console.error('Failed to create tab:', error)
    }
  }

  const closeTab = async (tabId: string) => {
    try {
      const response = await apiService.closeTab(tabId)
      if (response.success) {
        setTabs((prevTabs: Tab[]) => {
          const newTabs = prevTabs.filter((tab: Tab) => tab.id !== tabId)
          
          // If we closed the active tab, set a new active tab
          if (activeTab?.id === tabId) {
            if (newTabs.length > 0) {
              const newActiveTab = newTabs[0]
              setActiveTab({ ...newActiveTab, isActive: true })
            } else {
              setActiveTab(null)
            }
          }
          
          return newTabs
        })
      } else {
        console.error('Failed to close tab:', response.error)
      }
    } catch (error) {
      console.error('Failed to close tab:', error)
    }
  }

  const updateTab = async (tabId: string, updates: Partial<Tab>) => {
    try {
      const response = await apiService.updateTab(tabId, updates)
      if (response.success && response.data) {
        setTabs((prevTabs: Tab[]) =>
          prevTabs.map((tab: Tab) =>
            tab.id === tabId ? { ...tab, ...response.data } : tab
          )
        )
        
        if (activeTab?.id === tabId) {
          setActiveTab({ ...activeTab, ...response.data })
        }
      } else {
        console.error('Failed to update tab:', response.error)
      }
    } catch (error) {
      console.error('Failed to update tab:', error)
    }
  }

  const navigateTab = async (tabId: string, url: string) => {
    setLoading(true)
    try {
      const response = await apiService.navigateTab(tabId, url)
      if (response.success && response.data) {
        setTabs((prevTabs: Tab[]) =>
          prevTabs.map((tab: Tab) =>
            tab.id === tabId ? { ...tab, ...response.data } : tab
          )
        )
        
        if (activeTab?.id === tabId) {
          setActiveTab({ ...activeTab, ...response.data })
        }
      } else {
        console.error('Failed to navigate tab:', response.error)
      }
    } catch (error) {
      console.error('Failed to navigate tab:', error)
    } finally {
      setLoading(false)
    }
  }

  // Tab grouping functions
  const createTabGroup = (name: string, color: string, icon?: string) => {
    const newGroup: TabGroup = {
      id: `group-${Date.now()}`,
      name,
      color,
      icon,
      isCollapsed: false,
      tabIds: [],
      createdAt: new Date().toISOString()
    }
    setTabGroups(prev => [...prev, newGroup])
    return newGroup
  }

  const updateTabGroup = (groupId: string, updates: Partial<TabGroup>) => {
    setTabGroups(prev => 
      prev.map(group => 
        group.id === groupId ? { ...group, ...updates } : group
      )
    )
  }

  const deleteTabGroup = (groupId: string) => {
    // Move tabs out of group
    setTabs(prev => 
      prev.map(tab => 
        tab.groupId === groupId ? { ...tab, groupId: undefined } : tab
      )
    )
    setTabGroups(prev => prev.filter(group => group.id !== groupId))
  }

  const addTabToGroup = (tabId: string, groupId: string) => {
    setTabs(prev => 
      prev.map(tab => 
        tab.id === tabId ? { ...tab, groupId } : tab
      )
    )
    setTabGroups(prev => 
      prev.map(group => 
        group.id === groupId 
          ? { ...group, tabIds: [...group.tabIds, tabId] }
          : group
      )
    )
  }

  const removeTabFromGroup = (tabId: string) => {
    setTabs(prev => 
      prev.map(tab => 
        tab.id === tabId ? { ...tab, groupId: undefined } : tab
      )
    )
    setTabGroups(prev => 
      prev.map(group => ({
        ...group,
        tabIds: group.tabIds.filter(id => id !== tabId)
      }))
    )
  }

  // Tab pinning functions
  const pinTab = (tabId: string) => {
    setTabs(prev => 
      prev.map(tab => 
        tab.id === tabId ? { ...tab, isPinned: true } : tab
      )
    )
  }

  const unpinTab = (tabId: string) => {
    setTabs(prev => 
      prev.map(tab => 
        tab.id === tabId ? { ...tab, isPinned: false } : tab
      )
    )
  }

  // Tab duplication
  const duplicateTab = async (tabId: string) => {
    const tabToDuplicate = tabs.find(tab => tab.id === tabId)
    if (tabToDuplicate) {
      await createTab(tabToDuplicate.title, tabToDuplicate.url, tabToDuplicate.groupId)
    }
  }

  // Tab search and filtering
  const filteredTabs = tabs.filter(tab => 
    searchQuery === '' || 
    tab.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    tab.url.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const getTabsByGroup = (groupId?: string) => {
    return filteredTabs.filter(tab => tab.groupId === groupId)
  }

  const getUngroupedTabs = () => {
    return filteredTabs.filter(tab => !tab.groupId)
  }

  return {
    tabs: filteredTabs,
    tabGroups,
    activeTab,
    loading,
    searchQuery,
    setSearchQuery,
    createTab,
    closeTab,
    updateTab,
    navigateTab,
    setActiveTab,
    refreshTabs: loadTabs,
    // Group functions
    createTabGroup,
    updateTabGroup,
    deleteTabGroup,
    addTabToGroup,
    removeTabFromGroup,
    // Pin functions
    pinTab,
    unpinTab,
    // Duplicate function
    duplicateTab,
    // Filter functions
    getTabsByGroup,
    getUngroupedTabs
  }
}
