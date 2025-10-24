import React from 'react'

interface SearchEngine {
  id: string
  name: string
  url: string
  icon: string
  shortcut: string
  isDefault: boolean
}

interface SearchEnginesProps {
  engines: SearchEngine[]
  currentEngine: SearchEngine
  onEngineChange: (engine: SearchEngine) => void
}

export function SearchEngines({ engines, currentEngine, onEngineChange }: SearchEnginesProps) {
  return (
    <div className="flex items-center space-x-2">
      {engines.map((engine) => (
        <button
          key={engine.id}
          onClick={() => onEngineChange(engine)}
          className={`p-2 rounded-lg transition-colors ${
            currentEngine.id === engine.id
              ? 'bg-blue-600 text-white'
              : 'hover:bg-gray-700 text-gray-400 hover:text-white'
          }`}
          title={`${engine.name} (${engine.shortcut})`}
        >
          <span className="text-lg">{engine.icon}</span>
        </button>
      ))}
    </div>
  )
}
