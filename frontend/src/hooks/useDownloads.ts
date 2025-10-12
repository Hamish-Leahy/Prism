import { useState, useEffect } from 'react'
import { apiService } from '../services/api'

export interface Download {
  id: string
  filename: string
  url: string
  file_path: string
  file_size: number | null
  downloaded_size: number
  status: 'pending' | 'downloading' | 'paused' | 'completed' | 'cancelled' | 'error'
  created_at: string
  completed_at: string | null
  error_message: string | null
}

export const useDownloads = () => {
  const [downloads, setDownloads] = useState<Download[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    loadDownloads()
  }, [])

  const loadDownloads = async (status?: string, limit?: number, offset?: number) => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await apiService.getDownloads(status, limit, offset)
      if (response.success && response.data) {
        setDownloads(response.data)
      } else {
        setError(response.error || 'Failed to load downloads')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load downloads')
    } finally {
      setLoading(false)
    }
  }

  const createDownload = async (url: string, filename?: string) => {
    try {
      setError(null)
      
      const response = await apiService.createDownload(url, filename)
      if (response.success && response.data) {
        setDownloads(prev => [response.data, ...prev])
        return response.data
      } else {
        setError(response.error || 'Failed to create download')
        return null
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create download')
      return null
    }
  }

  const pauseDownload = async (id: string) => {
    try {
      const response = await apiService.pauseDownload(id)
      if (response.success) {
        setDownloads(prev =>
          prev.map(download =>
            download.id === id ? { ...download, status: 'paused' as const } : download
          )
        )
        return true
      } else {
        setError(response.error || 'Failed to pause download')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to pause download')
      return false
    }
  }

  const resumeDownload = async (id: string) => {
    try {
      const response = await apiService.resumeDownload(id)
      if (response.success) {
        setDownloads(prev =>
          prev.map(download =>
            download.id === id ? { ...download, status: 'downloading' as const } : download
          )
        )
        return true
      } else {
        setError(response.error || 'Failed to resume download')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to resume download')
      return false
    }
  }

  const cancelDownload = async (id: string) => {
    try {
      const response = await apiService.cancelDownload(id)
      if (response.success) {
        setDownloads(prev =>
          prev.map(download =>
            download.id === id ? { ...download, status: 'cancelled' as const } : download
          )
        )
        return true
      } else {
        setError(response.error || 'Failed to cancel download')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to cancel download')
      return false
    }
  }

  const deleteDownload = async (id: string) => {
    try {
      const response = await apiService.deleteDownload(id)
      if (response.success) {
        setDownloads(prev => prev.filter(download => download.id !== id))
        return true
      } else {
        setError(response.error || 'Failed to delete download')
        return false
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete download')
      return false
    }
  }

  const getDownloadById = (id: string): Download | undefined => {
    return downloads.find(download => download.id === id)
  }

  const getDownloadsByStatus = (status: Download['status']): Download[] => {
    return downloads.filter(download => download.status === status)
  }

  const getActiveDownloads = (): Download[] => {
    return downloads.filter(download => 
      download.status === 'downloading' || download.status === 'pending'
    )
  }

  const getCompletedDownloads = (): Download[] => {
    return downloads.filter(download => download.status === 'completed')
  }

  const getFailedDownloads = (): Download[] => {
    return downloads.filter(download => 
      download.status === 'error' || download.status === 'cancelled'
    )
  }

  const getTotalSize = (): number => {
    return downloads.reduce((total, download) => {
      return total + (download.file_size || 0)
    }, 0)
  }

  const getDownloadedSize = (): number => {
    return downloads.reduce((total, download) => {
      return total + download.downloaded_size
    }, 0)
  }

  const getProgress = (download: Download): number => {
    if (!download.file_size || download.file_size === 0) return 0
    return Math.round((download.downloaded_size / download.file_size) * 100)
  }

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B'
    
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  const formatSpeed = (bytesPerSecond: number): string => {
    return formatFileSize(bytesPerSecond) + '/s'
  }

  return {
    downloads,
    loading,
    error,
    loadDownloads,
    createDownload,
    pauseDownload,
    resumeDownload,
    cancelDownload,
    deleteDownload,
    getDownloadById,
    getDownloadsByStatus,
    getActiveDownloads,
    getCompletedDownloads,
    getFailedDownloads,
    getTotalSize,
    getDownloadedSize,
    getProgress,
    formatFileSize,
    formatSpeed,
    refreshDownloads: loadDownloads
  }
}
