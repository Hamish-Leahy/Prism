import { useState, useEffect } from 'react'
import { apiService } from '../services/api'

export interface Setting {
  key: string
  value: any
  updated_at: string
}

export interface SettingsByCategory {
  [category: string]: Setting[]
}

export const useSettings = () => {
  const [settings, setSettings] = useState<SettingsByCategory>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    loadSettings()
  }, [])

  const loadSettings = async (category?: string) => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await apiService.getSettings(category)
      if (response.success && response.data) {
        setSettings(response.data)
      } else {
        setError(response.error || 'Failed to load settings')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load settings')
    } finally {
      setLoading(false)
    }
  }

  const getSetting = (key: string, defaultValue: any = null): any => {
    for (const category in settings) {
      const setting = settings[category].find(s => s.key === key)
      if (setting) {
        return setting.value
      }
    }
    return defaultValue
  }

  const updateSetting = async (key: string, value: any, category: string = 'general') => {
    try {
      const response = await apiService.updateSetting(key, value, category)
      if (response.success) {
        // Update local state
        setSettings(prev => {
          const newSettings = { ...prev }
          if (!newSettings[category]) {
            newSettings[category] = []
          }
          
          const existingIndex = newSettings[category].findIndex(s => s.key === key)
          if (existingIndex >= 0) {
            newSettings[category][existingIndex] = {
              key,
              value,
              updated_at: new Date().toISOString()
            }
          } else {
            newSettings[category].push({
              key,
              value,
              updated_at: new Date().toISOString()
            })
          }
          
          return newSettings
        })
        return true
      } else {
        setError(response.error || 'Failed to update setting')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to update setting')
      return false
    }
  }

  const updateSettings = async (settingsToUpdate: Record<string, any>) => {
    try {
      const response = await apiService.updateSettings(settingsToUpdate)
      if (response.success) {
        // Reload all settings to get updated values
        await loadSettings()
        return true
      } else {
        setError(response.error || 'Failed to update settings')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to update settings')
      return false
    }
  }

  const resetSettings = async () => {
    try {
      const response = await apiService.resetSettings()
      if (response.success) {
        await loadSettings()
        return true
      } else {
        setError(response.error || 'Failed to reset settings')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to reset settings')
      return false
    }
  }

  // Convenience methods for common settings
  const getBrowserSettings = () => {
    return {
      defaultEngine: getSetting('browser.default_engine', 'prism'),
      homepage: getSetting('browser.homepage', 'about:blank'),
      newTabPage: getSetting('browser.new_tab_page', 'about:blank')
    }
  }

  const getPrivacySettings = () => {
    return {
      blockTrackers: getSetting('privacy.block_trackers', true),
      blockAds: getSetting('privacy.block_ads', true),
      clearDataOnExit: getSetting('privacy.clear_data_on_exit', false)
    }
  }

  const getAppearanceSettings = () => {
    return {
      theme: getSetting('appearance.theme', 'dark'),
      fontSize: getSetting('appearance.font_size', 14)
    }
  }

  const getPerformanceSettings = () => {
    return {
      cacheSize: getSetting('performance.cache_size', 100)
    }
  }

  const getSecuritySettings = () => {
    return {
      httpsOnly: getSetting('security.https_only', false)
    }
  }

  return {
    settings,
    loading,
    error,
    loadSettings,
    getSetting,
    updateSetting,
    updateSettings,
    resetSettings,
    getBrowserSettings,
    getPrivacySettings,
    getAppearanceSettings,
    getPerformanceSettings,
    getSecuritySettings,
    refreshSettings: loadSettings
  }
}