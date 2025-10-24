import SwiftUI
import WebKit

struct TorWebView: NSViewRepresentable {
    @ObservedObject var tab: BrowserTab
    
    func makeNSView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .nonPersistent() // Maximum privacy
        
        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.customUserAgent = "Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0" // Tor Browser UA
        
        // Configure proxy for Tor
        configureTorProxy(for: webView)
        
        return webView
    }
    
    func updateNSView(_ webView: WKWebView, context: Context) {
        if let url = tab.url, webView.url != url {
            let request = URLRequest(url: url)
            webView.load(request)
        }
    }
    
    func makeCoordinator() -> Coordinator {
        Coordinator(tab: tab)
    }
    
    func configureTorProxy(for webView: WKWebView) {
        // Configure SOCKS5 proxy for Tor
        // Note: This requires Tor to be running on localhost:9050
        let proxySettings: [String: Any] = [
            kCFNetworkProxiesSOCKSEnable as String: true,
            kCFNetworkProxiesSOCKSProxy as String: "127.0.0.1",
            kCFNetworkProxiesSOCKSPort as String: 9050
        ]
        
        // Apply to URLSession configuration
        let config = URLSessionConfiguration.default
        config.connectionProxyDictionary = proxySettings
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
            tab.url = webView.url
            tab.canGoBack = webView.canGoBack
            tab.canGoForward = webView.canGoForward
        }
        
        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            tab.isLoading = false
        }
    }
}

