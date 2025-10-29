import React, { useState, useEffect } from 'react'
import { Bookmark } from '../types/Bookmark'
import { apiService } from '../services/api'
import { Plus, Search, Folder, Star, Edit2, Trash2, ExternalLink, X } from 'lucide-react'

interface BookmarkManagerProps {
  onClose: () => void
}

export const BookmarkManager: React.FC<BookmarkManagerProps> = ({ onClose }) => {
  const [bookmarks, setBookmarks] = useState<Bookmark[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchQuery, setSearchQuery] = useState('')
  const [showAddForm, setShowAddForm] = useState(false)
  const [editingBookmark, setEditingBookmark] = useState<Bookmark | null>(null)
  const [newBookmark, setNewBookmark] = useState({
    title: '',
    url: ''
  })

  useEffect(() => {
    loadBookmarks()
  }, [])

  const loadBookmarks = async () => {
    try {
      setLoading(true)
      setError(null)
      
      const response = await apiService.getBookmarks()
      if (response.success && response.data) {
        setBookmarks(response.data)
      } else {
        setError(response.error || 'Failed to load bookmarks')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load bookmarks')
    } finally {
      setLoading(false)
    }
  }

  const handleAddBookmark = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!newBookmark.title || !newBookmark.url) {
      setError('Title and URL are required')
      return
    }

    try {
      const response = await apiService.createBookmark(
        newBookmark.title,
        newBookmark.url
      )
      
      if (response.success && response.data) {
        setBookmarks(prev => [response.data, ...prev])
        setNewBookmark({ title: '', url: '' })
        setShowAddForm(false)
        setError(null)
      } else {
        setError(response.error || 'Failed to create bookmark')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create bookmark')
    }
  }

  const handleEditBookmark = async (id: string, updates: Partial<Bookmark>) => {
    try {
      const response = await apiService.updateBookmark(id, updates)
      
      if (response.success && response.data) {
        setBookmarks(prev =>
          prev.map(bookmark =>
            bookmark.id === id ? response.data : bookmark
          )
        )
        setEditingBookmark(null)
        setError(null)
      } else {
        setError(response.error || 'Failed to update bookmark')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to update bookmark')
    }
  }

  const handleDeleteBookmark = async (id: string) => {
    if (!window.confirm('Are you sure you want to delete this bookmark?')) {
      return
    }

    try {
      const response = await apiService.deleteBookmark(id)
      
      if (response.success) {
        setBookmarks(prev => prev.filter(bookmark => bookmark.id !== id))
        setError(null)
      } else {
        setError(response.error || 'Failed to delete bookmark')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete bookmark')
    }
  }

  const filteredBookmarks = bookmarks.filter(bookmark =>
    bookmark.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    bookmark.url.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const openBookmark = (url: string) => {
    window.open(url, '_blank')
  }

  if (loading) {
    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-arc-surface border border-arc-border rounded-lg p-8">
          <div className="flex items-center space-x-3">
            <div className="w-6 h-6 border-2 border-arc-accent border-t-transparent rounded-full animate-spin"></div>
            <span className="text-arc-text">Loading bookmarks...</span>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-arc-surface border border-arc-border rounded-lg w-full max-w-6xl h-5/6 flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-arc-border">
          <div className="flex items-center space-x-3">
            <Star className="w-6 h-6 text-arc-accent" />
            <h2 className="text-xl font-semibold text-arc-text">Bookmark Manager</h2>
            <span className="text-sm text-arc-text-secondary">({bookmarks.length} bookmarks)</span>
          </div>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => setShowAddForm(true)}
              className="btn btn-primary flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>Add Bookmark</span>
            </button>
            <button
              onClick={onClose}
              className="p-2 hover:bg-arc-border rounded transition-colors"
            >
              <X className="w-5 h-5 text-arc-text-secondary" />
            </button>
          </div>
        </div>

        {/* Search and Filters */}
        <div className="p-4 border-b border-arc-border">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-arc-text-secondary" />
            <input
              type="text"
              placeholder="Search bookmarks..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="input pl-10 w-full"
            />
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4">
          {error && (
            <div className="bg-arc-error/10 border border-arc-error/20 rounded-lg p-3 mb-4">
              <p className="text-sm text-arc-error">{error}</p>
            </div>
          )}

          {showAddForm && (
            <div className="bg-arc-bg rounded-lg p-4 mb-4">
              <h3 className="text-lg font-medium text-arc-text mb-4">Add New Bookmark</h3>
              <form onSubmit={handleAddBookmark} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    Title
                  </label>
                  <input
                    type="text"
                    value={newBookmark.title}
                    onChange={(e) => setNewBookmark(prev => ({ ...prev, title: e.target.value }))}
                    className="input w-full"
                    placeholder="Bookmark title"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-arc-text mb-2">
                    URL
                  </label>
                  <input
                    type="url"
                    value={newBookmark.url}
                    onChange={(e) => setNewBookmark(prev => ({ ...prev, url: e.target.value }))}
                    className="input w-full"
                    placeholder="https://example.com"
                    required
                  />
                </div>
                <div className="flex justify-end space-x-2">
                  <button
                    type="button"
                    onClick={() => setShowAddForm(false)}
                    className="btn btn-secondary"
                  >
                    Cancel
                  </button>
                  <button type="submit" className="btn btn-primary">
                    Add Bookmark
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* Bookmarks List */}
          <div className="space-y-2">
            {filteredBookmarks.length === 0 ? (
              <div className="text-center py-12">
                <Star className="w-16 h-16 text-arc-text-secondary mx-auto mb-4" />
                <h3 className="text-lg font-medium text-arc-text mb-2">
                  {searchQuery ? 'No bookmarks found' : 'No bookmarks yet'}
                </h3>
                <p className="text-arc-text-secondary">
                  {searchQuery ? 'Try adjusting your search terms' : 'Add your first bookmark to get started'}
                </p>
              </div>
            ) : (
              filteredBookmarks.map((bookmark) => (
                <div
                  key={bookmark.id}
                  className="bg-arc-bg rounded-lg p-4 hover:bg-arc-border/50 transition-colors"
                >
                  {editingBookmark?.id === bookmark.id ? (
                    <EditBookmarkForm
                      bookmark={bookmark}
                      onSave={(updates) => handleEditBookmark(bookmark.id, updates)}
                      onCancel={() => setEditingBookmark(null)}
                    />
                  ) : (
                    <div className="flex items-center justify-between">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center space-x-2 mb-1">
                          <h4 className="text-sm font-medium text-arc-text truncate">
                            {bookmark.title}
                          </h4>
                          <button
                            onClick={() => openBookmark(bookmark.url)}
                            className="p-1 hover:bg-arc-border rounded transition-colors"
                            title="Open in new tab"
                          >
                            <ExternalLink className="w-3 h-3 text-arc-text-secondary" />
                          </button>
                        </div>
                        <p className="text-xs text-arc-text-secondary truncate">
                          {bookmark.url}
                        </p>
                      </div>
                      <div className="flex items-center space-x-1 ml-4">
                        <button
                          onClick={() => setEditingBookmark(bookmark)}
                          className="p-2 hover:bg-arc-border rounded transition-colors"
                          title="Edit bookmark"
                        >
                          <Edit2 className="w-4 h-4 text-arc-text-secondary" />
                        </button>
                        <button
                          onClick={() => handleDeleteBookmark(bookmark.id)}
                          className="p-2 hover:bg-arc-error/20 rounded transition-colors"
                          title="Delete bookmark"
                        >
                          <Trash2 className="w-4 h-4 text-arc-error" />
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

interface EditBookmarkFormProps {
  bookmark: Bookmark
  onSave: (updates: Partial<Bookmark>) => void
  onCancel: () => void
}

const EditBookmarkForm: React.FC<EditBookmarkFormProps> = ({
  bookmark,
  onSave,
  onCancel
}) => {
  const [formData, setFormData] = useState({
    title: bookmark.title,
    url: bookmark.url,
    description: bookmark.description || ''
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    onSave(formData)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3">
      <input
        type="text"
        value={formData.title}
        onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
        className="input w-full text-sm"
        placeholder="Bookmark title"
        required
      />
      <input
        type="url"
        value={formData.url}
        onChange={(e) => setFormData(prev => ({ ...prev, url: e.target.value }))}
        className="input w-full text-sm"
        placeholder="https://example.com"
        required
      />
      <textarea
        value={formData.description}
        onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
        className="input w-full text-sm h-16 resize-none"
        placeholder="Bookmark description"
      />
      <div className="flex justify-end space-x-2">
        <button
          type="button"
          onClick={onCancel}
          className="btn btn-secondary text-xs px-3 py-1"
        >
          Cancel
        </button>
        <button type="submit" className="btn btn-primary text-xs px-3 py-1">
          Save
        </button>
      </div>
    </form>
  )
}
