import { memo } from 'react'
import { BrowserPage } from './pages/BrowserPage'
import './styles/globals.css'
import './styles/components.css'

const App = memo(() => {
  return <BrowserPage />
})

App.displayName = 'App'

export default App
