import { useState, useEffect } from 'react'
import { Engine } from '../types/Engine'

export const useEngine = () => {
  const [engines, setEngines] = useState<Engine[]>([])
  const [currentEngine, setCurrentEngine] = useState<Engine | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadEngines()
    loadCurrentEngine()
  }, [])

  const loadEngines = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/engines')
      if (response.ok) {
        const data = await response.json()
        setEngines(data)
      }
    } catch (error) {
      console.error('Failed to load engines:', error)
    }
  }

  const loadCurrentEngine = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/engines/current')
      if (response.ok) {
        const data = await response.json()
        setCurrentEngine(data)
      }
    } catch (error) {
      console.error('Failed to load current engine:', error)
    } finally {
      setLoading(false)
    }
  }

  const switchEngine = async (engineId: string) => {
    try {
      const response = await fetch('http://localhost:8000/api/engines/switch', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ engine: engineId }),
      })

      if (response.ok) {
        const data = await response.json()
        if (data.success) {
          // Update current engine
          const engine = engines.find(e => e.id === engineId)
          if (engine) {
            setCurrentEngine(engine)
          }
        }
      }
    } catch (error) {
      console.error('Failed to switch engine:', error)
    }
  }

  return {
    engines,
    currentEngine,
    loading,
    switchEngine,
    refreshEngines: loadEngines
  }
}
