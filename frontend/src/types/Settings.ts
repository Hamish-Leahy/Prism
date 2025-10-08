export interface Settings {
  homePage: string
  searchEngine: 'google' | 'bing' | 'duckduckgo' | 'yahoo'
  showBookmarksBar: boolean
  autoSaveDownloads: boolean
  blockTrackers: boolean
  clearDataOnExit: boolean
  sendDoNotTrack: boolean
  theme: 'dark' | 'light' | 'auto'
  fontSize: 'small' | 'medium' | 'large'
  compactMode: boolean
  defaultEngine: 'chromium' | 'firefox' | 'prism'
  autoSwitchEngines: boolean
}
