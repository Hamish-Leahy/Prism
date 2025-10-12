export interface Tab {
  id: string
  title: string
  url: string
  isActive: boolean
  createdAt: string
  isPinned?: boolean
  groupId?: string
  favicon?: string
  isLoading?: boolean
  canGoBack?: boolean
  canGoForward?: boolean
}

export interface TabGroup {
  id: string
  name: string
  color: string
  icon?: string
  isCollapsed: boolean
  tabIds: string[]
  createdAt: string
}
