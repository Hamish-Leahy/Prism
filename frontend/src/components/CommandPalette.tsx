import React, { useState, useEffect, useRef } from 'react'

interface Tab {
  id: string
  title: string
  url: string
  favicon: string
  isActive: boolean
  isPinned: boolean
  groupId?: string
  searchEngine?: string
  canGoBack: boolean
  canGoForward: boolean
  isLoading: boolean
}

interface SearchEngine {
  id: string
  name: string
  url: string
  icon: string
  shortcut: string
  isDefault: boolean
}

interface CommandPaletteProps {
  onClose: () => void
  onSearch: (query: string, searchEngineId?: string) => void
  searchEngines: SearchEngine[]
  tabs: Tab[]
  onTabSelect: (tabId: string) => void
}

interface Command {
  id: string
  title: string
  description: string
  icon: string
  category: string
  action: () => void
}

export function CommandPalette({
  onClose,
  onSearch,
  searchEngines,
  tabs,
  onTabSelect
}: CommandPaletteProps) {
  const [query, setQuery] = useState('')
  const [selectedIndex, setSelectedIndex] = useState(0)
  const [filteredCommands, setFilteredCommands] = useState<Command[]>([])
  const inputRef = useRef<HTMLInputElement>(null)

  const allCommands: Command[] = [
    // Navigation Commands
    {
      id: 'new-tab',
      title: 'New Tab',
      description: 'Open a new tab',
      icon: '➕',
      category: 'Navigation',
      action: () => {
        // This would be handled by parent
        onClose()
      }
    },
    {
      id: 'close-tab',
      title: 'Close Tab',
      description: 'Close current tab',
      icon: '❌',
      category: 'Navigation',
      action: () => onClose()
    },
    {
      id: 'reload',
      title: 'Reload Page',
      description: 'Refresh current page',
      icon: '🔄',
      category: 'Navigation',
      action: () => onClose()
    },

    // Search Commands
    ...searchEngines.map(engine => ({
      id: `search-${engine.id}`,
      title: `Search with ${engine.name}`,
      description: `Search using ${engine.name}`,
      icon: engine.icon,
      category: 'Search',
      action: () => {
        onSearch(query, engine.id)
        onClose()
      }
    })),

    // Tab Commands
    ...tabs.map(tab => ({
      id: `tab-${tab.id}`,
      title: tab.title,
      description: tab.url,
      icon: tab.favicon,
      category: 'Tabs',
      action: () => {
        onTabSelect(tab.id)
        onClose()
      }
    })),

    // AI Commands
    {
      id: 'ai-summarize',
      title: 'Summarize Page',
      description: 'Get AI summary of current page',
      icon: '📝',
      category: 'AI',
      action: () => onClose()
    },
    {
      id: 'ai-translate',
      title: 'Translate Page',
      description: 'Translate current page to another language',
      icon: '🌐',
      category: 'AI',
      action: () => onClose()
    },
    {
      id: 'ai-explain',
      title: 'Explain Code',
      description: 'Get AI explanation of code on page',
      icon: '💻',
      category: 'AI',
      action: () => onClose()
    },

    // Performance Commands
    {
      id: 'perf-optimize',
      title: 'Optimize Performance',
      description: 'Optimize current page performance',
      icon: '⚡',
      category: 'Performance',
      action: () => onClose()
    },
    {
      id: 'perf-monitor',
      title: 'Performance Monitor',
      description: 'Open performance monitoring tools',
      icon: '📊',
      category: 'Performance',
      action: () => onClose()
    },

    // Security Commands
    {
      id: 'security-scan',
      title: 'Security Scan',
      description: 'Scan current page for security issues',
      icon: '🔒',
      category: 'Security',
      action: () => onClose()
    },
    {
      id: 'privacy-mode',
      title: 'Privacy Mode',
      description: 'Enable enhanced privacy protection',
      icon: '🕵️',
      category: 'Security',
      action: () => onClose()
    }
  ]

  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.focus()
    }
  }, [])

  useEffect(() => {
    if (query.trim() === '') {
      setFilteredCommands(allCommands)
    } else {
      const filtered = allCommands.filter(command =>
        command.title.toLowerCase().includes(query.toLowerCase()) ||
        command.description.toLowerCase().includes(query.toLowerCase()) ||
        command.category.toLowerCase().includes(query.toLowerCase())
      )
      setFilteredCommands(filtered)
    }
    setSelectedIndex(0)
  }, [query])

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose()
    } else if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedIndex(prev => Math.min(prev + 1, filteredCommands.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedIndex(prev => Math.max(prev - 1, 0))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (filteredCommands[selectedIndex]) {
        filteredCommands[selectedIndex].action()
      }
    }
  }

  const groupedCommands = filteredCommands.reduce((acc, command) => {
    if (!acc[command.category]) {
      acc[command.category] = []
    }
    acc[command.category].push(command)
    return acc
  }, {} as {[key: string]: Command[]})

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center pt-20 z-50">
      <div className="w-full max-w-2xl mx-4 bg-gray-900 rounded-lg shadow-2xl border border-gray-700">
        {/* Header */}
        <div className="p-4 border-b border-gray-700">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
              <span className="text-white font-bold">P</span>
            </div>
            <div>
              <h2 className="text-white font-semibold">Command Palette</h2>
              <p className="text-gray-400 text-sm">Type to search commands</p>
            </div>
          </div>
        </div>

        {/* Search Input */}
        <div className="p-4">
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Search commands..."
            className="w-full p-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        {/* Commands List */}
        <div className="max-h-96 overflow-y-auto">
          {Object.entries(groupedCommands).map(([category, commands]) => (
            <div key={category} className="mb-4">
              <div className="px-4 py-2 bg-gray-800 text-gray-400 text-xs uppercase tracking-wider font-medium">
                {category}
              </div>
              {commands.map((command, index) => {
                const globalIndex = filteredCommands.findIndex(c => c.id === command.id)
                return (
                  <button
                    key={command.id}
                    onClick={command.action}
                    className={`w-full px-4 py-3 text-left hover:bg-gray-800 transition-colors flex items-center space-x-3 ${
                      globalIndex === selectedIndex ? 'bg-blue-600 text-white' : 'text-gray-300'
                    }`}
                  >
                    <span className="text-lg">{command.icon}</span>
                    <div className="flex-1">
                      <div className="font-medium">{command.title}</div>
                      <div className="text-sm opacity-75">{command.description}</div>
                    </div>
                    {command.category === 'Search' && (
                      <div className="text-xs bg-gray-700 px-2 py-1 rounded">
                        {searchEngines.find(e => e.id === command.id.split('-')[1])?.shortcut}
                      </div>
                    )}
                  </button>
                )
              })}
            </div>
          ))}
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-700 bg-gray-800 rounded-b-lg">
          <div className="flex items-center justify-between text-sm text-gray-400">
            <div className="flex items-center space-x-4">
              <span>↑↓ Navigate</span>
              <span>↵ Select</span>
              <span>⎋ Close</span>
            </div>
            <div>
              {filteredCommands.length} command{filteredCommands.length !== 1 ? 's' : ''}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
