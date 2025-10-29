import { forwardRef, useEffect, useRef } from 'react'

interface Tab {
  id: string
  title: string
  url: string
  favicon: string
  isActive: boolean
  isPinned: boolean
  groupId?: string
  searchEngine?: string
  canGoBack: boolean
  canGoForward: boolean
  isLoading: boolean
}

interface WebViewProps {
  tab: Tab
  onTitleChange: (title: string) => void
  onFaviconChange: (favicon: string) => void
  onLoadingChange: (isLoading: boolean) => void
}

export const WebView = forwardRef<HTMLWebViewElement, WebViewProps>(({
  tab,
  onTitleChange,
  onFaviconChange,
  onLoadingChange
}) => {
  const webviewRef = useRef<HTMLWebViewElement>(null)

  useEffect(() => {
    const webview = webviewRef.current
    if (!webview) return

    const handleLoadStart = () => {
      onLoadingChange(true)
    }

    const handleLoadStop = () => {
      onLoadingChange(false)
    }

    const handlePageTitleUpdated = (event: any) => {
      onTitleChange(event.title || 'Untitled')
    }

    const handlePageFaviconUpdated = (event: any) => {
      onFaviconChange(event.favicons?.[0] || '🌐')
    }

    const handleDidNavigate = () => {
      onLoadingChange(false)
    }

    // Add event listeners
    webview.addEventListener('did-start-loading', handleLoadStart)
    webview.addEventListener('did-stop-loading', handleLoadStop)
    webview.addEventListener('page-title-updated', handlePageTitleUpdated)
    webview.addEventListener('page-favicon-updated', handlePageFaviconUpdated)
    webview.addEventListener('did-navigate', handleDidNavigate)

    return () => {
      webview.removeEventListener('did-start-loading', handleLoadStart)
      webview.removeEventListener('did-stop-loading', handleLoadStop)
      webview.removeEventListener('page-title-updated', handlePageTitleUpdated)
      webview.removeEventListener('page-favicon-updated', handlePageFaviconUpdated)
      webview.removeEventListener('did-navigate', handleDidNavigate)
    }
  }, [onTitleChange, onFaviconChange, onLoadingChange])

  useEffect(() => {
    const webview = webviewRef.current
    if (!webview || !tab.url) return

    if (tab.url === 'about:blank') {
      // Show a beautiful new tab page
      webview.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
          <title>Prism Browser</title>
          <style>
            body {
              margin: 0;
              padding: 0;
              background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
              height: 100vh;
              display: flex;
              align-items: center;
              justify-content: center;
              color: white;
            }
            .container {
              text-align: center;
              max-width: 600px;
              padding: 2rem;
            }
            .logo {
              font-size: 4rem;
              margin-bottom: 1rem;
              background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
              -webkit-background-clip: text;
              -webkit-text-fill-color: transparent;
              background-clip: text;
            }
            .title {
              font-size: 2.5rem;
              font-weight: 300;
              margin-bottom: 1rem;
            }
            .subtitle {
              font-size: 1.2rem;
              opacity: 0.8;
              margin-bottom: 2rem;
            }
            .features {
              display: grid;
              grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
              gap: 1rem;
              margin-top: 2rem;
            }
            .feature {
              background: rgba(255, 255, 255, 0.1);
              padding: 1rem;
              border-radius: 10px;
              backdrop-filter: blur(10px);
            }
            .feature-icon {
              font-size: 2rem;
              margin-bottom: 0.5rem;
            }
            .feature-title {
              font-weight: 600;
              margin-bottom: 0.5rem;
            }
            .feature-desc {
              font-size: 0.9rem;
              opacity: 0.8;
            }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="logo">🔮</div>
            <h1 class="title">Prism Browser</h1>
            <p class="subtitle">The revolutionary browser with AI-powered features</p>
            
            <div class="features">
              <div class="feature">
                <div class="feature-icon">🚀</div>
                <div class="feature-title">Multi-Engine</div>
                <div class="feature-desc">Prism, Chromium, Firefox engines</div>
              </div>
              <div class="feature">
                <div class="feature-icon">🤖</div>
                <div class="feature-title">AI Assistant</div>
                <div class="feature-desc">Intelligent browsing assistance</div>
              </div>
              <div class="feature">
                <div class="feature-icon">🔍</div>
                <div class="feature-title">Smart Search</div>
                <div class="feature-desc">8+ search engines integrated</div>
              </div>
              <div class="feature">
                <div class="feature-icon">⚡</div>
                <div class="feature-title">Performance</div>
                <div class="feature-desc">Real-time monitoring & optimization</div>
              </div>
            </div>
          </div>
        </body>
        </html>
      `
    } else {
      // Load the actual URL
      (webview as any).src = tab.url
    }
  }, [tab.url])

  return (
    <div className="h-full w-full relative">
      <webview
        ref={webviewRef}
        className="h-full w-full"
        style={{ display: 'flex' }}
        partition="persist:main"
        allowpopups
        webpreferences="contextIsolation=no, nodeIntegration=yes"
      />
      
      {/* Loading Overlay */}
      {tab.isLoading && (
        <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin" />
            <span className="text-white text-lg">Loading...</span>
          </div>
        </div>
      )}
    </div>
  )
})

WebView.displayName = 'WebView'
