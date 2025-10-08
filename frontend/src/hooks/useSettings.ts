import { useState, useEffect } from 'react'
import { Settings } from '../types/Settings'

const defaultSettings: Settings = {
  homePage: 'about:blank',
  searchEngine: 'google',
  showBookmarksBar: true,
  autoSaveDownloads: true,
  blockTrackers: true,
  clearDataOnExit: false,
  sendDoNotTrack: true,
  theme: 'dark',
  fontSize: 'medium',
  compactMode: false,
  defaultEngine: 'chromium',
  autoSwitchEngines: false
}

export const useSettings = () => {
  const [settings, setSettings] = useState<Settings>(defaultSettings)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadSettings()
  }, [])

  const loadSettings = async () => {
    try {
      // In a real app, this would load from the backend
      // For now, we'll load from localStorage
      const savedSettings = localStorage.getItem('prism-settings')
      if (savedSettings) {
        const parsedSettings = JSON.parse(savedSettings)
        setSettings({ ...defaultSettings, ...parsedSettings })
      }
    } catch (error) {
      console.error('Failed to load settings:', error)
    } finally {
      setLoading(false)
    }
  }

  const updateSettings = (newSettings: Settings) => {
    setSettings(newSettings)
    
    // Save to localStorage
    try {
      localStorage.setItem('prism-settings', JSON.stringify(newSettings))
    } catch (error) {
      console.error('Failed to save settings:', error)
    }
  }

  const resetSettings = () => {
    setSettings(defaultSettings)
    localStorage.removeItem('prism-settings')
  }

  return {
    settings,
    loading,
    updateSettings,
    resetSettings
  }
}
