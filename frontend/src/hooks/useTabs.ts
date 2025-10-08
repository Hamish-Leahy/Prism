import { useState, useEffect } from 'react'
import { Tab } from '../types/Tab'

export const useTabs = () => {
  const [tabs, setTabs] = useState<Tab[]>([])
  const [activeTab, setActiveTab] = useState<Tab | null>(null)

  useEffect(() => {
    loadTabs()
  }, [])

  const loadTabs = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/tabs')
      if (response.ok) {
        const data = await response.json()
        setTabs(data)
        
        // Set first tab as active if none is active
        if (data.length > 0 && !activeTab) {
          const firstActive = data.find((tab: Tab) => tab.isActive)
          setActiveTab(firstActive || data[0])
        }
      }
    } catch (error) {
      console.error('Failed to load tabs:', error)
    }
  }

  const createTab = async (title: string, url: string) => {
    try {
      const response = await fetch('http://localhost:8000/api/tabs', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ title, url }),
      })

      if (response.ok) {
        const data = await response.json()
        const newTab = { ...data, isActive: true }
        
        // Deactivate other tabs
        setTabs(prevTabs => 
          prevTabs.map(tab => ({ ...tab, isActive: false }))
        )
        
        // Add new tab
        setTabs(prevTabs => [...prevTabs, newTab])
        setActiveTab(newTab)
      }
    } catch (error) {
      console.error('Failed to create tab:', error)
    }
  }

  const closeTab = async (tabId: string) => {
    try {
      const response = await fetch(`http://localhost:8000/api/tabs/${tabId}`, {
        method: 'DELETE',
      })

      if (response.ok) {
        setTabs(prevTabs => {
          const newTabs = prevTabs.filter(tab => tab.id !== tabId)
          
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
      }
    } catch (error) {
      console.error('Failed to close tab:', error)
    }
  }

  const updateTab = async (tabId: string, updates: Partial<Tab>) => {
    try {
      const response = await fetch(`http://localhost:8000/api/tabs/${tabId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(updates),
      })

      if (response.ok) {
        const data = await response.json()
        setTabs(prevTabs =>
          prevTabs.map(tab =>
            tab.id === tabId ? { ...tab, ...data } : tab
          )
        )
        
        if (activeTab?.id === tabId) {
          setActiveTab({ ...activeTab, ...data })
        }
      }
    } catch (error) {
      console.error('Failed to update tab:', error)
    }
  }

  const navigateTab = async (tabId: string, url: string) => {
    try {
      const response = await fetch(`http://localhost:8000/api/tabs/${tabId}/navigate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url }),
      })

      if (response.ok) {
        const data = await response.json()
        setTabs(prevTabs =>
          prevTabs.map(tab =>
            tab.id === tabId ? { ...tab, ...data } : tab
          )
        )
        
        if (activeTab?.id === tabId) {
          setActiveTab({ ...activeTab, ...data })
        }
      }
    } catch (error) {
      console.error('Failed to navigate tab:', error)
    }
  }

  return {
    tabs,
    activeTab,
    createTab,
    closeTab,
    updateTab,
    navigateTab,
    setActiveTab,
    refreshTabs: loadTabs
  }
}
