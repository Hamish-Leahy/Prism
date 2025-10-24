import React, { useState, useRef, useEffect } from 'react'

interface SearchEngine {
  id: string
  name: string
  url: string
  icon: string
  shortcut: string
  isDefault: boolean
}

interface SmartAddressBarProps {
  currentUrl: string
  searchEngines: SearchEngine[]
  currentSearchEngine: SearchEngine
  onSearch: (query: string, searchEngineId?: string) => void
  onNavigate: (url: string) => void
  onSearchEngineChange: (engine: SearchEngine) => void
  onCommandPalette: () => void
}

export function SmartAddressBar({
  currentUrl,
  searchEngines,
  currentSearchEngine,
  onSearch,
  onNavigate,
  onSearchEngineChange,
  onCommandPalette
}: SmartAddressBarProps) {
  const [inputValue, setInputValue] = useState('')
  const [showSuggestions, setShowSuggestions] = useState(false)
  const [selectedSuggestion, setSelectedSuggestion] = useState(0)
  const [suggestions, setSuggestions] = useState<string[]>([])
  const [isSearchMode, setIsSearchMode] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    setInputValue(currentUrl)
  }, [currentUrl])

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value
    setInputValue(value)
    
    // Detect if it's a search query or URL
    const isUrl = value.includes('.') || value.startsWith('http') || value.startsWith('file://')
    setIsSearchMode(!isUrl)
    
    // Generate suggestions
    if (value.length > 0) {
      const searchSuggestions = [
        `${value} - Google Search`,
        `${value} - YouTube`,
        `${value} - GitHub`,
        `${value} - Stack Overflow`,
        `${value} - Reddit`,
        `${value} - Wikipedia`
      ]
      setSuggestions(searchSuggestions)
      setShowSuggestions(true)
    } else {
      setShowSuggestions(false)
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      if (isSearchMode) {
        onSearch(inputValue, currentSearchEngine.id)
      } else {
        onNavigate(inputValue.startsWith('http') ? inputValue : `https://${inputValue}`)
      }
      setShowSuggestions(false)
    } else if (e.key === 'Escape') {
      setShowSuggestions(false)
    } else if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedSuggestion(prev => Math.min(prev + 1, suggestions.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedSuggestion(prev => Math.max(prev - 1, 0))
    } else if (e.key === 'Tab') {
      e.preventDefault()
      if (suggestions[selectedSuggestion]) {
        setInputValue(suggestions[selectedSuggestion])
      }
    }
  }

  const handleSuggestionClick = (suggestion: string) => {
    setInputValue(suggestion)
    if (suggestion.includes(' - ')) {
      const [query, engine] = suggestion.split(' - ')
      const searchEngine = searchEngines.find(e => e.name === engine)
      if (searchEngine) {
        onSearch(query, searchEngine.id)
      }
    } else {
      onNavigate(suggestion)
    }
    setShowSuggestions(false)
  }

  const handleSearchEngineClick = (engine: SearchEngine) => {
    onSearchEngineChange(engine)
  }

  return (
    <div className="h-14 bg-gray-800 border-b border-gray-700 flex items-center px-4 space-x-4">
      {/* Navigation Buttons */}
      <div className="flex items-center space-x-1">
        <button className="p-2 hover:bg-gray-700 rounded-lg transition-colors text-gray-400 hover:text-white">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button className="p-2 hover:bg-gray-700 rounded-lg transition-colors text-gray-400 hover:text-white">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <button className="p-2 hover:bg-gray-700 rounded-lg transition-colors text-gray-400 hover:text-white">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>

      {/* Smart Address Bar */}
      <div className="flex-1 relative">
        <div className="flex items-center bg-gray-700 rounded-lg overflow-hidden">
          {/* Search Engine Selector */}
          <div className="flex items-center space-x-1 px-3 py-2 border-r border-gray-600">
            <span className="text-lg">{currentSearchEngine.icon}</span>
            <span className="text-sm text-gray-300">{currentSearchEngine.shortcut}</span>
          </div>

          {/* Address Input */}
          <input
            ref={inputRef}
            type="text"
            value={inputValue}
            onChange={handleInputChange}
            onKeyDown={handleKeyDown}
            onFocus={() => setShowSuggestions(true)}
            className="flex-1 bg-transparent px-4 py-2 text-white placeholder-gray-400 focus:outline-none"
            placeholder={isSearchMode ? `Search with ${currentSearchEngine.name}...` : 'Enter URL or search...'}
          />

          {/* Action Buttons */}
          <div className="flex items-center space-x-1 px-2">
            <button
              onClick={onCommandPalette}
              className="p-2 hover:bg-gray-600 rounded transition-colors text-gray-400 hover:text-white"
              title="Command Palette (Cmd+Shift+P)"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </button>
            <button className="p-2 hover:bg-gray-600 rounded transition-colors text-gray-400 hover:text-white">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
              </svg>
            </button>
          </div>
        </div>

        {/* Suggestions Dropdown */}
        {showSuggestions && suggestions.length > 0 && (
          <div className="absolute top-full left-0 right-0 mt-1 bg-gray-800 border border-gray-600 rounded-lg shadow-xl z-50">
            {suggestions.map((suggestion, index) => (
              <div
                key={index}
                className={`px-4 py-2 cursor-pointer transition-colors ${
                  index === selectedSuggestion 
                    ? 'bg-blue-600 text-white' 
                    : 'hover:bg-gray-700 text-gray-300'
                }`}
                onClick={() => handleSuggestionClick(suggestion)}
              >
                <div className="flex items-center space-x-3">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <span className="text-sm">{suggestion}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Search Engine Quick Selector */}
      <div className="flex items-center space-x-1">
        {searchEngines.slice(0, 4).map((engine) => (
          <button
            key={engine.id}
            onClick={() => handleSearchEngineClick(engine)}
            className={`p-2 rounded-lg transition-colors ${
              currentSearchEngine.id === engine.id
                ? 'bg-blue-600 text-white'
                : 'hover:bg-gray-700 text-gray-400 hover:text-white'
            }`}
            title={`${engine.name} (${engine.shortcut})`}
          >
            <span className="text-lg">{engine.icon}</span>
          </button>
        ))}
      </div>
    </div>
  )
}
