import React, { useState, useEffect } from 'react'
import { Tab } from '../types/Tab'
import { Engine } from '../types/Engine'

interface BrowserWindowProps {
  tab: Tab | null
  engine: Engine | null
  onTabUpdate?: (tabId: string, updates: Partial<Tab>) => void
  onNavigation?: (url: string) => void
}

export const BrowserWindow: React.FC<BrowserWindowProps> = ({ tab, engine, onTabUpdate, onNavigation }) => {
  const [content, setContent] = useState<string>('')
  const [loading, setLoading] = useState<boolean>(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (tab && tab.url !== 'about:blank') {
      loadPage(tab.url)
    } else {
      setContent('')
      setError(null)
    }
  }, [tab])

  const loadPage = async (url: string) => {
    setLoading(true)
    setError(null)
    
    try {
      const response = await fetch(`http://localhost:8000/api/tabs/${tab?.id}/navigate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url }),
      })
      
      const data = await response.json()
      
      if (!response.ok) {
        throw new Error(data.error || 'Failed to load page')
      }
      
      setContent(data.content || '')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load page')
    } finally {
      setLoading(false)
    }
  }

  if (!tab) {
    return (
      <div className="h-full flex items-center justify-center bg-arc-bg">
        <div className="text-center">
          <div className="w-16 h-16 bg-arc-surface rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-arc-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-arc-text mb-2">Welcome to Prism</h2>
          <p className="text-arc-text-secondary">Select a tab or create a new one to start browsing</p>
        </div>
      </div>
    )
  }

  if (tab.url === 'about:blank') {
    return (
      <div className="h-full flex items-center justify-center bg-arc-bg">
        <div className="text-center max-w-md">
          <div className="w-20 h-20 bg-arc-accent rounded-full flex items-center justify-center mx-auto mb-6">
            <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9" />
            </svg>
          </div>
          <h1 className="text-3xl font-bold text-arc-text mb-4">Prism Browser</h1>
          <p className="text-arc-text-secondary mb-8">
            A modern, privacy-focused browser with multiple rendering engines
          </p>
          <div className="space-y-4">
            <div className="text-left">
              <h3 className="font-semibold text-arc-text mb-2">Choose your engine:</h3>
              <div className="space-y-2">
                <div className="flex items-center space-x-3">
                  <div className="w-3 h-3 bg-arc-accent rounded-full"></div>
                  <span className="text-arc-text-secondary">Chromium - Full compatibility</span>
                </div>
                <div className="flex items-center space-x-3">
                  <div className="w-3 h-3 bg-arc-success rounded-full"></div>
                  <span className="text-arc-text-secondary">Firefox - Privacy focused</span>
                </div>
                <div className="flex items-center space-x-3">
                  <div className="w-3 h-3 bg-arc-warning rounded-full"></div>
                  <span className="text-arc-text-secondary">Prism - Lightweight & fast</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }

  if (loading) {
    return (
      <div className="h-full flex items-center justify-center bg-arc-bg">
        <div className="text-center">
          <div className="w-8 h-8 border-2 border-arc-accent border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-arc-text-secondary">Loading {tab.url}...</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="h-full flex items-center justify-center bg-arc-bg">
        <div className="text-center">
          <div className="w-16 h-16 bg-arc-error rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-arc-text mb-2">Failed to load page</h2>
          <p className="text-arc-text-secondary mb-4">{error}</p>
          <button
            onClick={() => loadPage(tab.url)}
            className="btn btn-primary"
          >
            Try Again
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="h-full bg-arc-bg">
      {content ? (
        <div 
          className="w-full h-full overflow-auto p-4"
          dangerouslySetInnerHTML={{ __html: content }}
        />
      ) : (
        <div className="w-full h-full flex items-center justify-center">
          <div className="text-center">
            <div className="w-16 h-16 bg-arc-surface rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-arc-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9" />
              </svg>
            </div>
            <h2 className="text-xl font-semibold text-arc-text mb-2">No content loaded</h2>
            <p className="text-arc-text-secondary">Enter a URL to start browsing</p>
          </div>
        </div>
      )}
    </div>
  )
}
