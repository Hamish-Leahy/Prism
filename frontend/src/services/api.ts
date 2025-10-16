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

export interface User {
  id: string
  username: string
  email: string
}

export interface AuthTokens {
  access_token: string
  refresh_token: string
  expires_in: number
}

export interface LoginResponse {
  success: boolean
  user?: User
  tokens?: AuthTokens
  error?: string
}

export interface RegisterResponse {
  success: boolean
  user_id?: string
  verification_token?: string
  error?: string
}

class ApiService {
  private accessToken: string | null = null
  private refreshToken: string | null = null

  constructor() {
    // Load tokens from localStorage on initialization
    this.loadTokens()
  }

  private loadTokens(): void {
    this.accessToken = localStorage.getItem('access_token')
    this.refreshToken = localStorage.getItem('refresh_token')
  }

  private saveTokens(tokens: AuthTokens): void {
    this.accessToken = tokens.access_token
    this.refreshToken = tokens.refresh_token
    localStorage.setItem('access_token', tokens.access_token)
    localStorage.setItem('refresh_token', tokens.refresh_token)
  }

  private clearTokens(): void {
    this.accessToken = null
    this.refreshToken = null
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    try {
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        ...options.headers,
      }

      // Add authorization header if we have an access token
      if (this.accessToken) {
        headers['Authorization'] = `Bearer ${this.accessToken}`
      }

      const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        ...options,
        headers,
      })

      // If we get a 401 and have a refresh token, try to refresh
      if (response.status === 401 && this.refreshToken && !endpoint.includes('/auth/')) {
        const refreshResult = await this.refreshAccessToken()
        if (refreshResult.success) {
          // Retry the original request with new token
          headers['Authorization'] = `Bearer ${this.accessToken}`
          const retryResponse = await fetch(`${API_BASE_URL}${endpoint}`, {
            ...options,
            headers,
          })
          return this.handleResponse<T>(retryResponse)
        } else {
          // Refresh failed, clear tokens and redirect to login
          this.clearTokens()
          window.location.href = '/login'
          return {
            success: false,
            error: 'Session expired. Please log in again.',
          }
        }
      }

      return this.handleResponse<T>(response)
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error occurred',
      }
    }
  }

  private async handleResponse<T>(response: Response): Promise<ApiResponse<T>> {
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
  }

  private async refreshAccessToken(): Promise<ApiResponse<AuthTokens>> {
    if (!this.refreshToken) {
      return {
        success: false,
        error: 'No refresh token available',
      }
    }

    try {
      const response = await fetch(`${API_BASE_URL}/auth/refresh`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ refresh_token: this.refreshToken }),
      })

      const data = await response.json()

      if (!response.ok) {
        return {
          success: false,
          error: data.error || 'Token refresh failed',
        }
      }

      this.saveTokens(data.tokens)
      return {
        success: true,
        data: data.tokens,
      }
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Token refresh failed',
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

  // Authentication API
  async register(username: string, email: string, password: string): Promise<ApiResponse<RegisterResponse>> {
    return this.request<RegisterResponse>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ username, email, password }),
    })
  }

  async login(usernameOrEmail: string, password: string): Promise<ApiResponse<LoginResponse>> {
    const response = await this.request<LoginResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username: usernameOrEmail, password }),
    })

    // Save tokens if login was successful
    if (response.success && response.data?.tokens) {
      this.saveTokens(response.data.tokens)
    }

    return response
  }

  async logout(): Promise<ApiResponse<{ success: boolean; message: string }>> {
    const response = await this.request<{ success: boolean; message: string }>('/auth/logout', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: this.refreshToken }),
    })

    // Clear tokens regardless of response
    this.clearTokens()

    return response
  }

  async verifyToken(): Promise<ApiResponse<{ success: boolean; user?: User; error?: string }>> {
    return this.request<{ success: boolean; user?: User; error?: string }>('/auth/verify')
  }

  async getProfile(): Promise<ApiResponse<{ success: boolean; user?: User; error?: string }>> {
    return this.request<{ success: boolean; user?: User; error?: string }>('/auth/profile')
  }

  async changePassword(currentPassword: string, newPassword: string): Promise<ApiResponse<{ success: boolean; error?: string }>> {
    return this.request<{ success: boolean; error?: string }>('/auth/change-password', {
      method: 'POST',
      body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
    })
  }

  async requestPasswordReset(email: string): Promise<ApiResponse<{ success: boolean; message: string; error?: string }>> {
    return this.request<{ success: boolean; message: string; error?: string }>('/auth/request-password-reset', {
      method: 'POST',
      body: JSON.stringify({ email }),
    })
  }

  async resetPassword(token: string, newPassword: string): Promise<ApiResponse<{ success: boolean; error?: string }>> {
    return this.request<{ success: boolean; error?: string }>('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify({ token, new_password: newPassword }),
    })
  }

  // Utility methods
  isAuthenticated(): boolean {
    return this.accessToken !== null
  }

  getCurrentUser(): User | null {
    // This would typically be stored in state or retrieved from the server
    // For now, we'll return null and let the app handle user state
    return null
  }

  // Health check
  async healthCheck(): Promise<ApiResponse<{ status: string; timestamp: number }>> {
    return this.request<{ status: string; timestamp: number }>('/health')
  }
}

export const apiService = new ApiService()
export default apiService
