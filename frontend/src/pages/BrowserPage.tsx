import React, { useState, useEffect } from 'react';
import { BrowserWindow } from '../components/BrowserWindow';
import { TabManager } from '../components/TabManager';
import { AddressBar } from '../components/AddressBar';
import { BookmarkBar } from '../components/BookmarkBar';
import { EngineSelector } from '../components/EngineSelector';
import { SettingsPanel } from '../components/SettingsPanel';
import { useTabs } from '../hooks/useTabs';
import { useEngine } from '../hooks/useEngine';
import { useSettings } from '../hooks/useSettings';
import { useDownloads } from '../hooks/useDownloads';
import { Tab } from '../types/Tab';
import { Engine } from '../types/Engine';
import { Settings } from '../types/Settings';

export const BrowserPage: React.FC = () => {
  const [showSettings, setShowSettings] = useState(false);
  const [showDownloads, setShowDownloads] = useState(false);
  const [showBookmarks, setShowBookmarks] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [activeTabId, setActiveTabId] = useState<string | null>(null);
  
  const { tabs, createTab, closeTab, updateTab, navigateTab, setActiveTab } = useTabs();
  const { engines, currentEngine, switchEngine, getEngineInfo } = useEngine();
  const { settings, updateSetting, resetSettings } = useSettings();
  const { downloads, pauseDownload, resumeDownload, cancelDownload, deleteDownload } = useDownloads();

  // Initialize with a default tab
  useEffect(() => {
    if (tabs.length === 0) {
      createTab('https://www.google.com', 'Google');
    }
  }, [tabs.length, createTab]);

  // Set active tab
  useEffect(() => {
    if (tabs.length > 0 && !activeTabId) {
      const activeTab = tabs.find(tab => tab.isActive) || tabs[0];
      setActiveTabId(activeTab.id);
    }
  }, [tabs, activeTabId]);

  const handleTabCreate = () => {
    createTab('https://www.google.com', 'New Tab');
  };

  const handleTabClose = (tabId: string) => {
    closeTab(tabId);
    if (activeTabId === tabId) {
      const remainingTabs = tabs.filter(tab => tab.id !== tabId);
      if (remainingTabs.length > 0) {
        setActiveTabId(remainingTabs[0].id);
      } else {
        setActiveTabId(null);
      }
    }
  };

  const handleTabSelect = (tabId: string) => {
    setActiveTabId(tabId);
    setActiveTab(tabId);
  };

  const handleTabUpdate = (tabId: string, updates: Partial<Tab>) => {
    updateTab(tabId, updates);
  };

  const handleNavigation = (url: string) => {
    if (activeTabId) {
      navigateTab(activeTabId, url);
    }
  };

  const handleEngineSwitch = (engineId: string) => {
    switchEngine(engineId);
  };

  const handleSettingUpdate = (key: string, value: any) => {
    updateSetting(key, value);
  };

  const handleDownloadAction = (downloadId: string, action: 'pause' | 'resume' | 'cancel' | 'delete') => {
    switch (action) {
      case 'pause':
        pauseDownload(downloadId);
        break;
      case 'resume':
        resumeDownload(downloadId);
        break;
      case 'cancel':
        cancelDownload(downloadId);
        break;
      case 'delete':
        deleteDownload(downloadId);
        break;
    }
  };

  const activeTab = tabs.find(tab => tab.id === activeTabId);

  return (
    <div className="h-screen flex flex-col bg-gray-100">
      {/* Top Toolbar */}
      <div className="bg-white border-b border-gray-300 flex items-center justify-between px-4 py-2">
        <div className="flex items-center space-x-4">
          <button
            onClick={() => setShowSettings(true)}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Settings"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          
          <button
            onClick={() => setShowBookmarks(true)}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Bookmarks"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
          </button>
          
          <button
            onClick={() => setShowHistory(true)}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="History"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
          
          <button
            onClick={() => setShowDownloads(true)}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Downloads"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </button>
        </div>

        <div className="flex items-center space-x-2">
          <EngineSelector
            engines={engines}
            currentEngine={currentEngine}
            onEngineChange={handleEngineSwitch}
          />
        </div>
      </div>

      {/* Address Bar */}
      <AddressBar
        url={activeTab?.url || ''}
        onNavigate={handleNavigation}
        onRefresh={() => activeTabId && navigateTab(activeTabId, activeTab?.url || '')}
        onBack={() => activeTabId && navigateTab(activeTabId, 'javascript:history.back()')}
        onForward={() => activeTabId && navigateTab(activeTabId, 'javascript:history.forward()')}
        canGoBack={activeTab?.canGoBack || false}
        canGoForward={activeTab?.canGoForward || false}
      />

      {/* Bookmark Bar */}
      <BookmarkBar />

      {/* Tab Manager */}
      <TabManager
        tabs={tabs}
        activeTabId={activeTabId}
        onTabCreate={handleTabCreate}
        onTabClose={handleTabClose}
        onTabSelect={handleTabSelect}
        onTabUpdate={handleTabUpdate}
      />

      {/* Main Browser Window */}
      <div className="flex-1 flex">
        {activeTab ? (
          <BrowserWindow
            tab={activeTab}
            onTabUpdate={handleTabUpdate}
            onNavigation={handleNavigation}
          />
        ) : (
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center">
              <h2 className="text-2xl font-bold text-gray-600 mb-4">No Active Tab</h2>
              <button
                onClick={handleTabCreate}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Create New Tab
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modals */}
      {showSettings && (
        <SettingsPanel
          settings={settings}
          onSettingUpdate={handleSettingUpdate}
          onClose={() => setShowSettings(false)}
        />
      )}

      {showDownloads && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-96 overflow-y-auto">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold">Downloads</h2>
              <button
                onClick={() => setShowDownloads(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <div className="space-y-2">
              {downloads.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No downloads</p>
              ) : (
                downloads.map(download => (
                  <div key={download.id} className="flex items-center justify-between p-3 border rounded-lg">
                    <div className="flex-1">
                      <p className="font-medium">{download.filename}</p>
                      <p className="text-sm text-gray-500">{download.url}</p>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div
                          className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                          style={{ width: `${download.progress}%` }}
                        />
                      </div>
                      <p className="text-xs text-gray-500 mt-1">
                        {download.progress}% - {download.status}
                      </p>
                    </div>
                    <div className="flex space-x-2 ml-4">
                      {download.status === 'downloading' && (
                        <button
                          onClick={() => handleDownloadAction(download.id, 'pause')}
                          className="px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600"
                        >
                          Pause
                        </button>
                      )}
                      {download.status === 'paused' && (
                        <button
                          onClick={() => handleDownloadAction(download.id, 'resume')}
                          className="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600"
                        >
                          Resume
                        </button>
                      )}
                      <button
                        onClick={() => handleDownloadAction(download.id, 'cancel')}
                        className="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={() => handleDownloadAction(download.id, 'delete')}
                        className="px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {showBookmarks && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-96 overflow-y-auto">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold">Bookmarks</h2>
              <button
                onClick={() => setShowBookmarks(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p className="text-gray-500 text-center py-4">Bookmark manager coming soon...</p>
          </div>
        </div>
      )}

      {showHistory && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-96 overflow-y-auto">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold">History</h2>
              <button
                onClick={() => setShowHistory(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p className="text-gray-500 text-center py-4">History manager coming soon...</p>
          </div>
        </div>
      )}
    </div>
  );
};
