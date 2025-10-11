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

  // Health check
  async healthCheck(): Promise<ApiResponse<{ status: string; timestamp: number }>> {
    return this.request<{ status: string; timestamp: number }>('/health')
  }
}

export const apiService = new ApiService()
export default apiService
