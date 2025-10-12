const API_BASE_URL = 'http://localhost:8000/api'

export interface ApiResponse<T = any> {
  data?: T
  error?: string
  success: boolean
}

export interface Tab {
  id: string
  title: string
  url: string
  isActive: boolean
  createdAt: string
}

export interface Engine {
  id: string
  name: string
  description: string
  enabled: boolean
  isCurrent: boolean
}

export interface NavigationResult {
  id: string
  title: string
  url: string
  isActive: boolean
  createdAt: string
  content?: string
  metadata?: {
    title: string
    url: string
    responseTime: number
    contentType: string
    contentLength: number
    server: string
    lastModified: string
  }
}

export interface Bookmark {
  id: string
  title: string
  url: string
  favicon?: string
  description?: string
  created_at: string
  updated_at: string
}

class ApiService {
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        headers: {
          'Content-Type': 'application/json',
          ...options.headers,
        },
        ...options,
      })

      const data = await response.json()

      if (!response.ok) {
        return {
          success: false,
          error: data.error || `HTTP ${response.status}: ${response.statusText}`,
        }
      }

      return {
        success: true,
        data,
      }
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error occurred',
      }
    }
  }

  // Tab API
  async getTabs(): Promise<ApiResponse<Tab[]>> {
    return this.request<Tab[]>('/tabs')
  }

  async createTab(title: string, url: string): Promise<ApiResponse<Tab>> {
    return this.request<Tab>('/tabs', {
      method: 'POST',
      body: JSON.stringify({ title, url }),
    })
  }

  async getTab(id: string): Promise<ApiResponse<Tab>> {
    return this.request<Tab>(`/tabs/${id}`)
  }

  async updateTab(id: string, updates: Partial<Tab>): Promise<ApiResponse<Tab>> {
    return this.request<Tab>(`/tabs/${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    })
  }

  async closeTab(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/tabs/${id}`, {
      method: 'DELETE',
    })
  }

  async navigateTab(id: string, url: string): Promise<ApiResponse<NavigationResult>> {
    return this.request<NavigationResult>(`/tabs/${id}/navigate`, {
      method: 'POST',
      body: JSON.stringify({ url }),
    })
  }

  async getTabContent(id: string): Promise<ApiResponse<{ content: string; metadata: any }>> {
    return this.request<{ content: string; metadata: any }>(`/tabs/${id}/content`)
  }

  async getTabMetadata(id: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/tabs/${id}/metadata`)
  }

  // Engine API
  async getEngines(): Promise<ApiResponse<Engine[]>> {
    return this.request<Engine[]>('/engines')
  }

  async getCurrentEngine(): Promise<ApiResponse<Engine>> {
    return this.request<Engine>('/engines/current')
  }

  async switchEngine(engineId: string): Promise<ApiResponse<{ success: boolean; engine: string }>> {
    return this.request<{ success: boolean; engine: string }>('/engines/switch', {
      method: 'POST',
      body: JSON.stringify({ engine: engineId }),
    })
  }

  async getEngineStatus(engineId: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/engines/${engineId}/status`)
  }

  async getEngineInfo(engineId: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/engines/${engineId}/info`)
  }

  async getEngineStats(engineId: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/engines/${engineId}/stats`)
  }

  // Settings API
  async getSettings(category?: string): Promise<ApiResponse<any>> {
    const endpoint = category ? `/settings?category=${category}` : '/settings'
    return this.request<any>(endpoint)
  }

  async getSetting(key: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/settings/${key}`)
  }

  async updateSetting(key: string, value: any, category?: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/settings/${key}`, {
      method: 'PUT',
      body: JSON.stringify({ value, category }),
    })
  }

  async updateSettings(settings: Record<string, any>): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>('/settings', {
      method: 'PUT',
      body: JSON.stringify(settings),
    })
  }

  async resetSettings(): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>('/settings/reset', {
      method: 'POST',
    })
  }

  // Downloads API
  async getDownloads(status?: string, limit?: number, offset?: number): Promise<ApiResponse<any[]>> {
    const params = new URLSearchParams()
    if (status) params.append('status', status)
    if (limit) params.append('limit', limit.toString())
    if (offset) params.append('offset', offset.toString())
    
    const endpoint = params.toString() ? `/downloads?${params}` : '/downloads'
    return this.request<any[]>(endpoint)
  }

  async createDownload(url: string, filename?: string): Promise<ApiResponse<any>> {
    return this.request<any>('/downloads', {
      method: 'POST',
      body: JSON.stringify({ url, filename }),
    })
  }

  async getDownload(id: string): Promise<ApiResponse<any>> {
    return this.request<any>(`/downloads/${id}`)
  }

  async pauseDownload(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/downloads/${id}/pause`, {
      method: 'POST',
    })
  }

  async resumeDownload(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/downloads/${id}/resume`, {
      method: 'POST',
    })
  }

  async cancelDownload(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/downloads/${id}/cancel`, {
      method: 'POST',
    })
  }

  async deleteDownload(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/downloads/${id}`, {
      method: 'DELETE',
    })
  }

  // Bookmarks API
  async getBookmarks(): Promise<ApiResponse<Bookmark[]>> {
    return this.request<Bookmark[]>('/bookmarks')
  }

  async createBookmark(title: string, url: string, description?: string): Promise<ApiResponse<Bookmark>> {
    return this.request<Bookmark>('/bookmarks', {
      method: 'POST',
      body: JSON.stringify({ title, url, description }),
    })
  }

  async getBookmark(id: string): Promise<ApiResponse<Bookmark>> {
    return this.request<Bookmark>(`/bookmarks/${id}`)
  }

  async updateBookmark(id: string, updates: Partial<Bookmark>): Promise<ApiResponse<Bookmark>> {
    return this.request<Bookmark>(`/bookmarks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    })
  }

  async deleteBookmark(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/bookmarks/${id}`, {
      method: 'DELETE',
    })
  }

  // History API
  async getHistory(limit?: number, offset?: number): Promise<ApiResponse<any[]>> {
    const params = new URLSearchParams()
    if (limit) params.append('limit', limit.toString())
    if (offset) params.append('offset', offset.toString())
    
    const endpoint = params.toString() ? `/history?${params}` : '/history'
    return this.request<any[]>(endpoint)
  }

  async addHistoryEntry(title: string, url: string): Promise<ApiResponse<any>> {
    return this.request<any>('/history', {
      method: 'POST',
      body: JSON.stringify({ title, url }),
    })
  }

  async deleteHistoryEntry(id: string): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>(`/history/${id}`, {
      method: 'DELETE',
    })
  }

  async clearHistory(): Promise<ApiResponse<{ success: boolean }>> {
    return this.request<{ success: boolean }>('/history', {
      method: 'DELETE',
    })
  }

  // Health check
  async healthCheck(): Promise<ApiResponse<{ status: string; timestamp: number }>> {
    return this.request<{ status: string; timestamp: number }>('/health')
  }
}

export const apiService = new ApiService()
export default apiService
