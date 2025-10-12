import React, { useState } from 'react'
import { X, Monitor, Shield, Palette, Globe, Settings as SettingsIcon } from 'lucide-react'
import { useSettings } from '../hooks/useSettings'

interface SettingsPanelProps {
  onClose: () => void
}

export const SettingsPanel: React.FC<SettingsPanelProps> = ({ onClose }) => {
  const [activeTab, setActiveTab] = useState('general')
  const {
    settings,
    loading,
    error,
    updateSetting,
    updateSettings,
    resetSettings,
    getBrowserSettings,
    getPrivacySettings,
    getAppearanceSettings,
    getPerformanceSettings,
    getSecuritySettings
  } = useSettings()

  const handleSettingChange = async (key: string, value: any, category: string = 'general') => {
    await updateSetting(key, value, category)
  }

  const handleMultipleSettingsChange = async (settingsToUpdate: Record<string, any>) => {
    await updateSettings(settingsToUpdate)
  }

  const handleResetSettings = async () => {
    if (window.confirm('Are you sure you want to reset all settings to default?')) {
      await resetSettings()
    }
  }

  if (loading) {
    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-arc-surface border border-arc-border rounded-lg p-8">
          <div className="flex items-center space-x-3">
            <div className="w-6 h-6 border-2 border-arc-accent border-t-transparent rounded-full animate-spin"></div>
            <span className="text-arc-text">Loading settings...</span>
          </div>
        </div>
      </div>
    )
  }

  const tabs = [
    { id: 'general', label: 'General', icon: Monitor },
    { id: 'privacy', label: 'Privacy', icon: Shield },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'engines', label: 'Engines', icon: Globe },
    { id: 'performance', label: 'Performance', icon: SettingsIcon },
  ]

  const browserSettings = getBrowserSettings()
  const privacySettings = getPrivacySettings()
  const appearanceSettings = getAppearanceSettings()
  const performanceSettings = getPerformanceSettings()
  const securitySettings = getSecuritySettings()

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-arc-surface border border-arc-border rounded-lg w-full max-w-4xl h-5/6 flex">
        {/* Sidebar */}
        <div className="w-64 bg-arc-bg border-r border-arc-border p-4">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold text-arc-text">Settings</h2>
            <button
              onClick={onClose}
              className="p-1 hover:bg-arc-border rounded transition-colors"
            >
              <X className="w-5 h-5 text-arc-text-secondary" />
            </button>
          </div>
          
          <nav className="space-y-1">
            {tabs.map((tab) => {
              const Icon = tab.icon
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center space-x-3 px-3 py-2 rounded-lg transition-colors ${
                    activeTab === tab.id
                      ? 'bg-arc-accent text-white'
                      : 'text-arc-text-secondary hover:text-arc-text hover:bg-arc-border'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  <span>{tab.label}</span>
                </button>
              )
            })}
          </nav>
        </div>

        {/* Content */}
        <div className="flex-1 p-6 overflow-y-auto">
          {activeTab === 'general' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h3 className="text-xl font-semibold text-arc-text">General Settings</h3>
                <button
                  onClick={handleResetSettings}
                  className="text-sm text-arc-text-secondary hover:text-arc-text transition-colors"
                >
                  Reset to Default
                </button>
              </div>
              
              {error && (
                <div className="bg-arc-error/10 border border-arc-error/20 rounded-lg p-3">
                  <p className="text-sm text-arc-error">{error}</p>
                </div>
              )}
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    Home Page
                  </label>
                  <input
                    type="url"
                    value={browserSettings.homepage}
                    onChange={(e) => handleSettingChange('browser.homepage', e.target.value, 'general')}
                    className="input w-full"
                    placeholder="https://example.com"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    New Tab Page
                  </label>
                  <input
                    type="url"
                    value={browserSettings.newTabPage}
                    onChange={(e) => handleSettingChange('browser.new_tab_page', e.target.value, 'general')}
                    className="input w-full"
                    placeholder="about:blank"
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Show Bookmarks Bar
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Display bookmarks below the address bar
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={true} // This would come from settings
                    onChange={(e) => handleSettingChange('browser.show_bookmarks_bar', e.target.checked, 'general')}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>
              </div>
            </div>
          )}

          {activeTab === 'privacy' && (
            <div className="space-y-6">
              <h3 className="text-xl font-semibold text-arc-text">Privacy Settings</h3>
              
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Block Trackers
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Block tracking scripts and cookies
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={privacySettings.blockTrackers}
                    onChange={(e) => handleSettingChange('privacy.block_trackers', e.target.checked, 'privacy')}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Block Ads
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Block advertisements and ad networks
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={privacySettings.blockAds}
                    onChange={(e) => handleSettingChange('privacy.block_ads', e.target.checked, 'privacy')}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Clear Data on Exit
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Clear browsing data when closing the browser
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={privacySettings.clearDataOnExit}
                    onChange={(e) => handleSettingChange('privacy.clear_data_on_exit', e.target.checked, 'privacy')}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>
              </div>
            </div>
          )}

          {activeTab === 'appearance' && (
            <div className="space-y-6">
              <h3 className="text-xl font-semibold text-arc-text">Appearance Settings</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    Theme
                  </label>
                  <select
                    value={settings.theme}
                    onChange={(e) => handleSettingChange('theme', e.target.value)}
                    className="input w-full"
                  >
                    <option value="dark">Dark</option>
                    <option value="light">Light</option>
                    <option value="auto">Auto</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    Font Size
                  </label>
                  <select
                    value={settings.fontSize}
                    onChange={(e) => handleSettingChange('fontSize', e.target.value)}
                    className="input w-full"
                  >
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                  </select>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Compact Mode
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Use compact interface elements
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={settings.compactMode}
                    onChange={(e) => handleSettingChange('compactMode', e.target.checked)}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>
              </div>
            </div>
          )}

          {activeTab === 'engines' && (
            <div className="space-y-6">
              <h3 className="text-xl font-semibold text-arc-text">Engine Settings</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    Default Engine
                  </label>
                  <select
                    value={settings.defaultEngine}
                    onChange={(e) => handleSettingChange('defaultEngine', e.target.value)}
                    className="input w-full"
                  >
                    <option value="chromium">Chromium</option>
                    <option value="firefox">Firefox</option>
                    <option value="prism">Prism</option>
                  </select>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <label className="text-sm font-medium text-arc-text">
                      Auto-switch for Compatibility
                    </label>
                    <p className="text-xs text-arc-text-secondary">
                      Automatically switch engines for better compatibility
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    checked={settings.autoSwitchEngines}
                    onChange={(e) => handleSettingChange('autoSwitchEngines', e.target.checked)}
                    className="w-4 h-4 text-arc-accent bg-arc-surface border-arc-border rounded focus:ring-arc-accent"
                  />
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
