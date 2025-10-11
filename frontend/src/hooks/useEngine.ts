import { useState, useEffect } from 'react'
import { Engine } from '../types/Engine'
import { apiService } from '../services/api'

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
      const response = await apiService.getEngines()
      if (response.success && response.data) {
        setEngines(response.data)
      } else {
        console.error('Failed to load engines:', response.error)
      }
    } catch (error) {
      console.error('Failed to load engines:', error)
    }
  }

  const loadCurrentEngine = async () => {
    try {
      const response = await apiService.getCurrentEngine()
      if (response.success && response.data) {
        setCurrentEngine(response.data)
      } else {
        console.error('Failed to load current engine:', response.error)
      }
    } catch (error) {
      console.error('Failed to load current engine:', error)
    } finally {
      setLoading(false)
    }
  }

  const switchEngine = async (engineId: string) => {
    try {
      const response = await apiService.switchEngine(engineId)
      if (response.success) {
        // Update current engine
        const engine = engines.find((e: Engine) => e.id === engineId)
        if (engine) {
          setCurrentEngine(engine)
        }
      } else {
        console.error('Failed to switch engine:', response.error)
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
