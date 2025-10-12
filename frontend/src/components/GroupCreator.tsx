import React, { useState } from 'react'
import { Plus, Palette, Tag, X } from 'lucide-react'

interface GroupCreatorProps {
  onCreateGroup: (name: string, color: string, icon?: string) => void
  onClose: () => void
}

const GROUP_COLORS = [
  '#3B82F6', // Blue
  '#EF4444', // Red
  '#10B981', // Green
  '#F59E0B', // Yellow
  '#8B5CF6', // Purple
  '#EC4899', // Pink
  '#06B6D4', // Cyan
  '#84CC16', // Lime
  '#F97316', // Orange
  '#6366F1', // Indigo
]

const GROUP_ICONS = [
  '🏠', '💼', '🎯', '📚', '🎨', '🔧', '📊', '🎵', '🎮', '📱',
  '💡', '🚀', '⭐', '❤️', '🔥', '💎', '🌟', '🎪', '🎭', '🎨'
]

export const GroupCreator: React.FC<GroupCreatorProps> = ({
  onCreateGroup,
  onClose
}) => {
  const [name, setName] = useState('')
  const [selectedColor, setSelectedColor] = useState(GROUP_COLORS[0])
  const [selectedIcon, setSelectedIcon] = useState(GROUP_ICONS[0])
  const [showIconPicker, setShowIconPicker] = useState(false)

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (name.trim()) {
      onCreateGroup(name.trim(), selectedColor, selectedIcon)
      onClose()
    }
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-arc-surface border border-arc-border rounded-lg p-6 w-96 max-w-full mx-4">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-arc-text">Create Tab Group</h3>
          <button
            onClick={onClose}
            className="p-1 hover:bg-arc-border rounded transition-colors"
          >
            <X className="w-5 h-5 text-arc-text-secondary" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Group Name */}
          <div>
            <label className="block text-sm font-medium text-arc-text mb-2">
              Group Name
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Enter group name..."
              className="w-full px-3 py-2 bg-arc-bg border border-arc-border rounded-lg text-arc-text placeholder-arc-text-secondary focus:outline-none focus:ring-2 focus:ring-arc-accent focus:border-transparent"
              autoFocus
            />
          </div>

          {/* Color Selection */}
          <div>
            <label className="block text-sm font-medium text-arc-text mb-2">
              <Palette className="w-4 h-4 inline mr-1" />
              Color
            </label>
            <div className="grid grid-cols-5 gap-2">
              {GROUP_COLORS.map((color) => (
                <button
                  key={color}
                  type="button"
                  onClick={() => setSelectedColor(color)}
                  className={`w-8 h-8 rounded-full border-2 transition-all ${
                    selectedColor === color
                      ? 'border-arc-text scale-110'
                      : 'border-arc-border hover:scale-105'
                  }`}
                  style={{ backgroundColor: color }}
                />
              ))}
            </div>
          </div>

          {/* Icon Selection */}
          <div>
            <label className="block text-sm font-medium text-arc-text mb-2">
              <Tag className="w-4 h-4 inline mr-1" />
              Icon (Optional)
            </label>
            <div className="flex items-center space-x-2">
              <button
                type="button"
                onClick={() => setShowIconPicker(!showIconPicker)}
                className="flex items-center space-x-2 px-3 py-2 bg-arc-bg border border-arc-border rounded-lg hover:bg-arc-border transition-colors"
              >
                <span className="text-lg">{selectedIcon}</span>
                <span className="text-sm text-arc-text-secondary">Choose icon</span>
              </button>
            </div>
            
            {showIconPicker && (
              <div className="mt-2 p-3 bg-arc-bg border border-arc-border rounded-lg">
                <div className="grid grid-cols-10 gap-1 max-h-32 overflow-y-auto">
                  {GROUP_ICONS.map((icon) => (
                    <button
                      key={icon}
                      type="button"
                      onClick={() => {
                        setSelectedIcon(icon)
                        setShowIconPicker(false)
                      }}
                      className="w-8 h-8 flex items-center justify-center hover:bg-arc-border rounded transition-colors"
                    >
                      <span className="text-lg">{icon}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Preview */}
          <div className="p-3 bg-arc-bg border border-arc-border rounded-lg">
            <div className="flex items-center space-x-2">
              <div 
                className="w-3 h-3 rounded-full"
                style={{ backgroundColor: selectedColor }}
              />
              <span className="text-lg">{selectedIcon}</span>
              <span className="text-sm text-arc-text">
                {name || 'Group Name'}
              </span>
            </div>
          </div>

          {/* Actions */}
          <div className="flex justify-end space-x-2 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-arc-text-secondary hover:text-arc-text transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!name.trim()}
              className="px-4 py-2 bg-arc-accent text-white text-sm font-medium rounded-lg hover:bg-arc-accent-dark disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Create Group
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
