import React, { useState, useEffect } from 'react'
import { Bookmark } from '../types/Bookmark'
import { apiService } from '../services/api'
import { Star, Plus, Settings } from 'lucide-react'
import { BookmarkManager } from './BookmarkManager'

interface BookmarkBarProps {
  onBookmarkClick?: (bookmark: Bookmark) => void
  onAddBookmark?: () => void
}

export const BookmarkBar: React.FC<BookmarkBarProps> = ({
  onBookmarkClick,
  onAddBookmark
}) => {
  const [bookmarks, setBookmarks] = useState<Bookmark[]>([])
  const [showBookmarkManager, setShowBookmarkManager] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadBookmarks()
  }, [])

  const loadBookmarks = async () => {
    try {
      setLoading(true)
      const response = await apiService.getBookmarks()
      if (response.success && response.data) {
        setBookmarks(response.data)
      } else {
        console.error('Failed to load bookmarks:', response.error)
      }
    } catch (error) {
      console.error('Failed to load bookmarks:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleBookmarkClick = (bookmark: Bookmark) => {
    if (onBookmarkClick) {
      onBookmarkClick(bookmark)
    }
  }

  return (
    <div className="h-10 bg-arc-surface border-b border-arc-border flex items-center px-4 space-x-2 overflow-x-auto">
      {/* Add Bookmark Button */}
      <button
        onClick={onAddBookmark}
        className="flex-shrink-0 p-1 hover:bg-arc-border rounded transition-colors"
        title="Add Bookmark"
      >
        <Plus className="w-4 h-4 text-arc-text-secondary" />
      </button>

      {/* Bookmark Separator */}
      <div className="w-px h-6 bg-arc-border"></div>

      {/* Bookmarks */}
      <div className="flex items-center space-x-1 min-w-0">
        {bookmarks.length === 0 ? (
          <div className="text-xs text-arc-text-secondary">
            No bookmarks yet
          </div>
        ) : (
          bookmarks.map((bookmark) => (
            <button
              key={bookmark.id}
              onClick={() => handleBookmarkClick(bookmark)}
              className="flex-shrink-0 flex items-center space-x-1 px-2 py-1 hover:bg-arc-border rounded transition-colors group"
              title={bookmark.title}
            >
              {bookmark.favicon ? (
                <img
                  src={bookmark.favicon}
                  alt=""
                  className="w-4 h-4"
                  onError={(e) => {
                    e.currentTarget.style.display = 'none'
                  }}
                />
              ) : (
                <Star className="w-3 h-3 text-arc-text-secondary group-hover:text-arc-accent" />
              )}
              <span className="text-xs text-arc-text-secondary group-hover:text-arc-text truncate max-w-24">
                {bookmark.title}
              </span>
            </button>
          ))
        )}
      </div>

      {/* Overflow Indicator */}
      {bookmarks.length > 8 && (
        <div className="flex-shrink-0 text-xs text-arc-text-secondary">
          +{bookmarks.length - 8} more
        </div>
      )}
    </div>
  )
}
