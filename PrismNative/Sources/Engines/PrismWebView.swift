import SwiftUI
import WebKit

struct PrismWebView: NSViewRepresentable {
    @ObservedObject var tab: BrowserTab
    
    func makeNSView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .default()
        
        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.customUserAgent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Prism/1.0.0 (KHTML, like Gecko) Safari/605.1.15"
        
        return webView
    }
    
    func updateNSView(_ webView: WKWebView, context: Context) {
        if let url = tab.url {
            // Check if it's a prism:// protocol
            if url.scheme == "prism" {
                loadPrismContent(for: url, in: webView)
            } else if webView.url != url {
                // Use backend proxy for rendering
                loadThroughBackend(url: url, in: webView)
            }
        }
    }
    
    func loadPrismContent(for url: URL, in webView: WKWebView) {
        let html = """
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Prism Browser</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 40px;
                }
                .container {
                    text-align: center;
                    max-width: 800px;
                }
                .logo { font-size: 100px; margin-bottom: 30px; }
                h1 { font-size: 48px; font-weight: 700; margin-bottom: 20px; }
                p { font-size: 20px; opacity: 0.9; margin-bottom: 40px; }
                .features {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-top: 40px;
                }
                .feature {
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 12px;
                    padding: 30px;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }
                .feature h3 { margin: 15px 0 10px; font-size: 18px; }
                .feature p { font-size: 14px; opacity: 0.8; margin: 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="logo">🔮</div>
                <h1>Prism Browser</h1>
                <p>Next-Generation Multi-Engine Browser</p>
                
                <div class="features">
                    <div class="feature">
                        <div style="font-size: 32px;">🔵</div>
                        <h3>Safari</h3>
                        <p>Native WebKit performance</p>
                    </div>
                    <div class="feature">
                        <div style="font-size: 32px;">🟠</div>
                        <h3>Firefox</h3>
                        <p>Privacy-enhanced browsing</p>
                    </div>
                    <div class="feature">
                        <div style="font-size: 32px;">🟣</div>
                        <h3>Tor</h3>
                        <p>Maximum anonymity</p>
                    </div>
                    <div class="feature">
                        <div style="font-size: 32px;">🟢</div>
                        <h3>Prism</h3>
                        <p>Custom rendering engine</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        """
        
        webView.loadHTMLString(html, baseURL: nil)
    }
    
    func loadThroughBackend(url: URL, in webView: WKWebView) {
        // Call backend API to render through Prism engine
        let backendURL = URL(string: "http://localhost:8000/api/engine/navigate")!
        var request = URLRequest(url: backendURL)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let body: [String: Any] = [
            "url": url.absoluteString,
            "engine": "prism"
        ]
        
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            guard let data = data,
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let html = json["html"] as? String else {
                // Fallback to direct load
                DispatchQueue.main.async {
                    webView.load(URLRequest(url: url))
                }
                return
            }
            
            DispatchQueue.main.async {
                webView.loadHTMLString(html, baseURL: url)
            }
        }.resume()
    }
    
    func makeCoordinator() -> Coordinator {
        Coordinator(tab: tab)
    }
    
    class Coordinator: NSObject, WKNavigationDelegate {
        var tab: BrowserTab
        
        init(tab: BrowserTab) {
            self.tab = tab
        }
        
        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            tab.isLoading = true
        }
        
        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            tab.isLoading = false
            tab.title = webView.title ?? "New Tab"
            tab.canGoBack = webView.canGoBack
            tab.canGoForward = webView.canGoForward
        }
        
        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            tab.isLoading = false
        }
    }
}

