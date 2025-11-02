import { memo, forwardRef, useEffect, useRef, useCallback } from 'react'

interface Tab {
  id: string
  title: string
  url: string
  favicon?: string
  isActive: boolean
  isPinned?: boolean
  groupId?: string
  searchEngine?: string
  canGoBack?: boolean
  canGoForward?: boolean
  isLoading?: boolean
}

interface WebViewProps {
  tab: Tab
  onTitleChange: (title: string) => void
  onFaviconChange: (favicon: string) => void
  onLoadingChange: (isLoading: boolean) => void
  onNavigationChange?: (url: string) => void
  onCanGoBackChange?: (canGoBack: boolean) => void
  onCanGoForwardChange?: (canGoForward: boolean) => void
}

const NewTabPage = memo(() => (
  <div className="h-full w-full flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
    <div className="text-center max-w-2xl px-6 py-12">
      <div className="mb-8">
        <div className="inline-block p-4 bg-gradient-to-br from-blue-600 to-purple-600 rounded-3xl shadow-2xl transform rotate-3 hover:rotate-0 transition-transform duration-300">
          <span className="text-6xl">🔮</span>
        </div>
      </div>
      
      <h1 className="text-5xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-4">
        Prism Browser
      </h1>
      
      <p className="text-xl text-gray-600 dark:text-gray-300 mb-12">
        Revolutionary multi-engine browser with AI-powered features
      </p>
      
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
        <div className="p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
          <div className="text-3xl mb-2">🚀</div>
          <div className="font-semibold text-gray-900 dark:text-white">Multi-Engine</div>
          <div className="text-sm text-gray-600 dark:text-gray-400">3 engines</div>
        </div>
        <div className="p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
          <div className="text-3xl mb-2">🤖</div>
          <div className="font-semibold text-gray-900 dark:text-white">AI Assistant</div>
          <div className="text-sm text-gray-600 dark:text-gray-400">Smart help</div>
        </div>
        <div className="p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
          <div className="text-3xl mb-2">🔍</div>
          <div className="font-semibold text-gray-900 dark:text-white">Smart Search</div>
          <div className="text-sm text-gray-600 dark:text-gray-400">8+ engines</div>
        </div>
        <div className="p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
          <div className="text-3xl mb-2">⚡</div>
          <div className="font-semibold text-gray-900 dark:text-white">Fast & Smooth</div>
          <div className="text-sm text-gray-600 dark:text-gray-400">Optimized</div>
        </div>
      </div>
      
      <div className="text-sm text-gray-500 dark:text-gray-400">
        Type a URL or search query in the address bar above
      </div>
    </div>
  </div>
))

NewTabPage.displayName = 'NewTabPage'

export const WebView = memo(forwardRef<HTMLWebViewElement, WebViewProps>(({
  tab,
  onTitleChange,
  onFaviconChange,
  onLoadingChange,
  onNavigationChange,
  onCanGoBackChange,
  onCanGoForwardChange
}, forwardedRef) => {
  const webviewRef = useRef<HTMLWebViewElement>(null)
  const internalRef = webviewRef.current || (forwardedRef as React.RefObject<HTMLWebViewElement>)?.current

  const handleLoadStart = useCallback(() => {
    onLoadingChange(true)
  }, [onLoadingChange])

  const handleLoadStop = useCallback(() => {
    onLoadingChange(false)
  }, [onLoadingChange])

  const handlePageTitleUpdated = useCallback((event: any) => {
    onTitleChange(event.title || 'Untitled')
  }, [onTitleChange])

  const handlePageFaviconUpdated = useCallback((event: any) => {
    onFaviconChange(event.favicons?.[0] || '🌐')
  }, [onFaviconChange])

  const handleDidNavigate = useCallback((event: any) => {
    onLoadingChange(false)
    if (onNavigationChange && event.url) {
      onNavigationChange(event.url)
    }
  }, [onLoadingChange, onNavigationChange])

  const handleCanGoBackChange = useCallback((event: any) => {
    if (onCanGoBackChange) {
      onCanGoBackChange(event.canGoBack ?? false)
    }
  }, [onCanGoBackChange])

  const handleCanGoForwardChange = useCallback((event: any) => {
    if (onCanGoForwardChange) {
      onCanGoForwardChange(event.canGoForward ?? false)
    }
  }, [onCanGoForwardChange])

  useEffect(() => {
    const webview = internalRef
    if (!webview) return

    webview.addEventListener('did-start-loading', handleLoadStart)
    webview.addEventListener('did-stop-loading', handleLoadStop)
    webview.addEventListener('page-title-updated', handlePageTitleUpdated)
    webview.addEventListener('page-favicon-updated', handlePageFaviconUpdated)
    webview.addEventListener('did-navigate', handleDidNavigate)
    webview.addEventListener('did-navigate-in-page', handleDidNavigate)

    return () => {
      webview.removeEventListener('did-start-loading', handleLoadStart)
      webview.removeEventListener('did-stop-loading', handleLoadStop)
      webview.removeEventListener('page-title-updated', handlePageTitleUpdated)
      webview.removeEventListener('page-favicon-updated', handlePageFaviconUpdated)
      webview.removeEventListener('did-navigate', handleDidNavigate)
      webview.removeEventListener('did-navigate-in-page', handleDidNavigate)
    }
  }, [internalRef, handleLoadStart, handleLoadStop, handlePageTitleUpdated, handlePageFaviconUpdated, handleDidNavigate])

  useEffect(() => {
    const webview = internalRef
    if (!webview || !tab.url) return

    if (tab.url === 'about:blank' || tab.url === '') {
      return // NewTabPage component will handle this
    }

    // Only update src if it's different to avoid unnecessary reloads
    if ((webview as any).src !== tab.url) {
      (webview as any).src = tab.url
    }
  }, [tab.url, internalRef])

  if (tab.url === 'about:blank' || tab.url === '') {
    return <NewTabPage />
  }

  return (
    <div 
      className="h-full w-full relative bg-white dark:bg-gray-950"
      style={{
        willChange: 'contents',
        transform: 'translateZ(0)'
      }}
    >
      <webview
        ref={webviewRef}
        className="h-full w-full"
        style={{ 
          display: 'flex',
          willChange: 'auto'
        }}
        partition="persist:main"
        allowpopups
        webpreferences="contextIsolation=no, nodeIntegration=yes, webviewTag=true"
      />
      
      {/* Loading Overlay */}
      {tab.isLoading && (
        <div 
          className="absolute inset-0 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm flex items-center justify-center z-10"
          style={{
            willChange: 'opacity',
            transform: 'translateZ(0)'
          }}
        >
          <div className="flex flex-col items-center space-y-4">
            <div className="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
            <span className="text-gray-700 dark:text-gray-300 font-medium">Loading...</span>
          </div>
        </div>
      )}
    </div>
  )
}))

WebView.displayName = 'WebView'
