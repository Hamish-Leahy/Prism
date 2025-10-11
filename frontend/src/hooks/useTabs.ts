import { useState, useEffect } from 'react'
import { Tab } from '../types/Tab'
import { apiService } from '../services/api'

export const useTabs = () => {
  const [tabs, setTabs] = useState<Tab[]>([])
  const [activeTab, setActiveTab] = useState<Tab | null>(null)
  const [loading, setLoading] = useState<boolean>(false)

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

  const createTab = async (title: string, url: string) => {
    try {
      const response = await apiService.createTab(title, url)
      if (response.success && response.data) {
        const newTab = { ...response.data, isActive: true }
        
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

  return {
    tabs,
    activeTab,
    loading,
    createTab,
    closeTab,
    updateTab,
    navigateTab,
    setActiveTab,
    refreshTabs: loadTabs
  }
}
