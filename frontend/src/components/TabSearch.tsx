import React from 'react'
import { Search, X } from 'lucide-react'

interface TabSearchProps {
  searchQuery: string
  onSearchChange: (query: string) => void
  resultCount: number
}

export const TabSearch: React.FC<TabSearchProps> = ({
  searchQuery,
  onSearchChange,
  resultCount
}) => {
  return (
    <div className="p-4 border-b border-arc-border">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-arc-text-secondary" />
        <input
          type="text"
          placeholder="Search tabs..."
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          className="w-full pl-10 pr-10 py-2 bg-arc-surface border border-arc-border rounded-lg text-sm text-arc-text placeholder-arc-text-secondary focus:outline-none focus:ring-2 focus:ring-arc-accent focus:border-transparent"
        />
        {searchQuery && (
          <button
            onClick={() => onSearchChange('')}
            className="absolute right-3 top-1/2 transform -translate-y-1/2 p-1 hover:bg-arc-border rounded transition-colors"
          >
            <X className="w-4 h-4 text-arc-text-secondary" />
          </button>
        )}
      </div>
      {searchQuery && (
        <div className="mt-2 text-xs text-arc-text-secondary">
          {resultCount} tab{resultCount !== 1 ? 's' : ''} found
        </div>
      )}
    </div>
  )
}
