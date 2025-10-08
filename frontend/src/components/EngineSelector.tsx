import React, { useState } from 'react'
import { Engine } from '../types/Engine'
import { ChevronDown, Check } from 'lucide-react'

interface EngineSelectorProps {
  engines: Engine[]
  currentEngine: Engine | null
  onEngineChange: (engineId: string) => void
}

export const EngineSelector: React.FC<EngineSelectorProps> = ({
  engines,
  currentEngine,
  onEngineChange
}) => {
  const [isOpen, setIsOpen] = useState(false)

  const handleEngineSelect = (engineId: string) => {
    onEngineChange(engineId)
    setIsOpen(false)
  }

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 px-3 py-1 bg-arc-surface border border-arc-border rounded-lg hover:bg-arc-border transition-colors"
      >
        <div className={`w-2 h-2 rounded-full ${
          currentEngine?.id === 'chromium' ? 'bg-arc-accent' :
          currentEngine?.id === 'firefox' ? 'bg-arc-success' :
          currentEngine?.id === 'prism' ? 'bg-arc-warning' :
          'bg-arc-text-secondary'
        }`}></div>
        <span className="text-sm text-arc-text">
          {currentEngine?.name || 'Select Engine'}
        </span>
        <ChevronDown className={`w-4 h-4 text-arc-text-secondary transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute top-full left-0 mt-1 w-64 bg-arc-surface border border-arc-border rounded-lg shadow-lg z-50">
          <div className="p-2">
            <div className="text-xs font-medium text-arc-text-secondary uppercase tracking-wide mb-2 px-2">
              Rendering Engines
            </div>
            {engines.map((engine) => (
              <button
                key={engine.id}
                onClick={() => handleEngineSelect(engine.id)}
                className="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-arc-border transition-colors group"
              >
                <div className="flex items-center space-x-3">
                  <div className={`w-3 h-3 rounded-full ${
                    engine.id === 'chromium' ? 'bg-arc-accent' :
                    engine.id === 'firefox' ? 'bg-arc-success' :
                    engine.id === 'prism' ? 'bg-arc-warning' :
                    'bg-arc-text-secondary'
                  }`}></div>
                  <div className="text-left">
                    <div className="font-medium text-arc-text group-hover:text-arc-accent">
                      {engine.name}
                    </div>
                    <div className="text-xs text-arc-text-secondary">
                      {engine.description}
                    </div>
                  </div>
                </div>
                {currentEngine?.id === engine.id && (
                  <Check className="w-4 h-4 text-arc-accent" />
                )}
              </button>
            ))}
          </div>
          
          <div className="border-t border-arc-border p-2">
            <div className="text-xs text-arc-text-secondary px-2">
              Current: {currentEngine?.name || 'None'}
            </div>
          </div>
        </div>
      )}

      {/* Overlay to close dropdown */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setIsOpen(false)}
        />
      )}
    </div>
  )
}
