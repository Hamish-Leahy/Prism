import React, { useState, useRef, useEffect } from 'react'
import { Search, RefreshCw, ArrowLeft, ArrowRight, Home } from 'lucide-react'

interface AddressBarProps {
  onNavigate?: (url: string) => void
  onRefresh?: () => void
  onBack?: () => void
  onForward?: () => void
  onHome?: () => void
  currentUrl?: string
  loading?: boolean
}

export const AddressBar: React.FC<AddressBarProps> = ({
  onNavigate,
  onRefresh,
  onBack,
  onForward,
  onHome,
  currentUrl = '',
  loading = false
}) => {
  const [inputValue, setInputValue] = useState(currentUrl)
  const [isFocused, setIsFocused] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    setInputValue(currentUrl)
  }, [currentUrl])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (onNavigate && inputValue.trim()) {
      let url = inputValue.trim()
      
      // Add protocol if missing
      if (!url.match(/^https?:\/\//)) {
        if (url.includes('.') && !url.includes(' ')) {
          url = `https://${url}`
        } else {
          // Treat as search query
          url = `https://www.google.com/search?q=${encodeURIComponent(url)}`
        }
      }
      
      onNavigate(url)
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      inputRef.current?.blur()
      setInputValue(currentUrl)
    }
  }

  const isUrl = (str: string) => {
    try {
      new URL(str)
      return true
    } catch {
      return false
    }
  }

  return (
    <div className="h-12 bg-arc-surface border-b border-arc-border flex items-center px-4 space-x-3">
      {/* Navigation Buttons */}
      <div className="flex items-center space-x-1">
        <button
          onClick={onBack}
          className="p-2 hover:bg-arc-border rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          disabled={!onBack}
          title="Back"
        >
          <ArrowLeft className="w-4 h-4 text-arc-text-secondary" />
        </button>
        <button
          onClick={onForward}
          className="p-2 hover:bg-arc-border rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          disabled={!onForward}
          title="Forward"
        >
          <ArrowRight className="w-4 h-4 text-arc-text-secondary" />
        </button>
        <button
          onClick={onRefresh}
          className="p-2 hover:bg-arc-border rounded transition-colors"
          title="Refresh"
        >
          <RefreshCw className={`w-4 h-4 text-arc-text-secondary ${loading ? 'animate-spin' : ''}`} />
        </button>
        <button
          onClick={onHome}
          className="p-2 hover:bg-arc-border rounded transition-colors"
          title="Home"
        >
          <Home className="w-4 h-4 text-arc-text-secondary" />
        </button>
      </div>

      {/* Address Bar */}
      <form onSubmit={handleSubmit} className="flex-1">
        <div className={`relative flex items-center bg-arc-bg border border-arc-border rounded-lg transition-all ${
          isFocused ? 'ring-2 ring-arc-accent border-arc-accent' : ''
        }`}>
          <div className="pl-3 pr-2">
            {isUrl(inputValue) ? (
              <div className="w-4 h-4 text-arc-accent">
                <svg fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clipRule="evenodd" />
                </svg>
              </div>
            ) : (
              <Search className="w-4 h-4 text-arc-text-secondary" />
            )}
          </div>
          
          <input
            ref={inputRef}
            type="text"
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onFocus={() => setIsFocused(true)}
            onBlur={() => setIsFocused(false)}
            onKeyDown={handleKeyDown}
            placeholder="Search or enter address"
            className="flex-1 bg-transparent text-arc-text placeholder-arc-text-secondary py-2 pr-3 focus:outline-none"
          />
          
          {loading && (
            <div className="pr-3">
              <div className="w-4 h-4 border-2 border-arc-accent border-t-transparent rounded-full animate-spin"></div>
            </div>
          )}
        </div>
      </form>

      {/* Quick Actions */}
      <div className="flex items-center space-x-1">
        <button
          className="p-2 hover:bg-arc-border rounded transition-colors"
          title="Bookmarks"
        >
          <svg className="w-4 h-4 text-arc-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </button>
        <button
          className="p-2 hover:bg-arc-border rounded transition-colors"
          title="Downloads"
        >
          <svg className="w-4 h-4 text-arc-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </button>
      </div>
    </div>
  )
}
