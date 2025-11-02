import React, { memo, useState, useRef, useEffect, useCallback, useMemo } from 'react'
import { Search, RefreshCw, ArrowLeft, ArrowRight, Home, Lock, Globe } from 'lucide-react'

interface AddressBarProps {
  onNavigate?: (url: string) => void
  onRefresh?: () => void
  onBack?: () => void
  onForward?: () => void
  onHome?: () => void
  currentUrl?: string
  loading?: boolean
  canGoBack?: boolean
  canGoForward?: boolean
}

// Debounce helper
function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value)

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value)
    }, delay)

    return () => {
      clearTimeout(handler)
    }
  }, [value, delay])

  return debouncedValue
}

export const AddressBar = memo<AddressBarProps>(({
  onNavigate,
  onRefresh,
  onBack,
  onForward,
  onHome,
  currentUrl = '',
  loading = false,
  canGoBack = false,
  canGoForward = false
}) => {
  const [inputValue, setInputValue] = useState(currentUrl)
  const [isFocused, setIsFocused] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  // Sync with currentUrl prop
  useEffect(() => {
    if (!isFocused) {
      setInputValue(currentUrl)
    }
  }, [currentUrl, isFocused])

  const normalizeUrl = useCallback((url: string): string => {
    const trimmed = url.trim()
    
    // Already a valid URL
    if (/^https?:\/\//i.test(trimmed)) {
      return trimmed
    }
    
    // Has domain-like structure
    if (/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)+(\.[a-zA-Z]{2,})/.test(trimmed)) {
      return `https://${trimmed}`
    }
    
    // Treat as search query
    return `https://www.google.com/search?q=${encodeURIComponent(trimmed)}`
  }, [])

  const handleSubmit = useCallback((e: React.FormEvent) => {
    e.preventDefault()
    if (onNavigate && inputValue.trim()) {
      const url = normalizeUrl(inputValue)
      onNavigate(url)
      inputRef.current?.blur()
    }
  }, [inputValue, onNavigate, normalizeUrl])

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      inputRef.current?.blur()
      setInputValue(currentUrl)
    } else if (e.key === 'Enter') {
      handleSubmit(e)
    }
  }, [currentUrl, handleSubmit])

  const isSecure = useMemo(() => {
    return currentUrl.startsWith('https://') || currentUrl.startsWith('about:')
  }, [currentUrl])

  const isUrl = useMemo(() => {
    if (!inputValue) return false
    try {
      new URL(inputValue.startsWith('http') ? inputValue : `https://${inputValue}`)
      return true
    } catch {
      return false
    }
  }, [inputValue])

  const displayUrl = useMemo(() => {
    try {
      if (currentUrl && (currentUrl.startsWith('http://') || currentUrl.startsWith('https://'))) {
        const url = new URL(currentUrl)
        return url.hostname + url.pathname
      }
      return currentUrl
    } catch {
      return currentUrl
    }
  }, [currentUrl])

  return (
    <div 
      className="h-14 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 flex items-center px-4 gap-3"
      style={{
        willChange: 'contents',
        transform: 'translateZ(0)'
      }}
    >
      {/* Navigation Buttons */}
      <div className="flex items-center gap-1">
        <button
          onClick={onBack}
          disabled={!canGoBack}
          className="p-2 rounded-lg transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-800 active:scale-95"
          title="Back (Alt+←)"
          aria-label="Back"
          style={{ transform: 'translateZ(0)' }}
        >
          <ArrowLeft className="w-5 h-5 text-gray-600 dark:text-gray-400" />
        </button>
        <button
          onClick={onForward}
          disabled={!canGoForward}
          className="p-2 rounded-lg transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-800 active:scale-95"
          title="Forward (Alt+→)"
          aria-label="Forward"
          style={{ transform: 'translateZ(0)' }}
        >
          <ArrowRight className="w-5 h-5 text-gray-600 dark:text-gray-400" />
        </button>
        <button
          onClick={onRefresh}
          className="p-2 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-800 active:scale-95"
          title="Refresh (Cmd+R)"
          aria-label="Refresh"
          style={{ transform: 'translateZ(0)' }}
        >
          <RefreshCw className={`w-5 h-5 text-gray-600 dark:text-gray-400 ${loading ? 'animate-spin' : ''}`} />
        </button>
        <button
          onClick={onHome}
          className="p-2 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-800 active:scale-95"
          title="Home"
          aria-label="Home"
          style={{ transform: 'translateZ(0)' }}
        >
          <Home className="w-5 h-5 text-gray-600 dark:text-gray-400" />
        </button>
      </div>

      {/* Address Bar */}
      <form onSubmit={handleSubmit} className="flex-1 max-w-3xl mx-auto">
        <div 
          className={`relative flex items-center bg-gray-50 dark:bg-gray-900 border-2 rounded-xl transition-all duration-200 ${
            isFocused 
              ? 'border-blue-500 dark:border-blue-400 shadow-lg shadow-blue-500/20' 
              : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
          }`}
          style={{
            willChange: 'border-color, box-shadow',
            transform: 'translateZ(0)'
          }}
        >
          <div className="pl-4 pr-2 flex items-center">
            {isFocused ? (
              isUrl ? (
                <Globe className="w-4 h-4 text-gray-500 dark:text-gray-400" />
              ) : (
                <Search className="w-4 h-4 text-gray-500 dark:text-gray-400" />
              )
            ) : (
              isSecure ? (
                <Lock className="w-4 h-4 text-green-600 dark:text-green-400" />
              ) : (
                <Globe className="w-4 h-4 text-gray-400" />
              )
            )}
          </div>
          
          <input
            ref={inputRef}
            type="text"
            value={isFocused ? inputValue : displayUrl}
            onChange={(e) => setInputValue(e.target.value)}
            onFocus={() => setIsFocused(true)}
            onBlur={() => setIsFocused(false)}
            onKeyDown={handleKeyDown}
            placeholder="Search or enter address"
            className="flex-1 bg-transparent text-sm font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 py-3 pr-4 focus:outline-none"
            spellCheck={false}
            autoComplete="off"
          />
          
          {loading && (
            <div className="pr-4">
              <div className="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
            </div>
          )}
        </div>
      </form>

      {/* Quick Actions */}
      <div className="flex items-center gap-1">
        <button
          className="p-2 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-800 active:scale-95"
          title="Bookmarks (Cmd+Shift+B)"
          aria-label="Bookmarks"
          style={{ transform: 'translateZ(0)' }}
        >
          <svg className="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </button>
      </div>
    </div>
  )
})

AddressBar.displayName = 'AddressBar'
