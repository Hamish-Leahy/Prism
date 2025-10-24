import SwiftUI
import WebKit

struct SafariWebView: NSViewRepresentable {
    @ObservedObject var tab: BrowserTab
    
    func makeNSView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .default()
        
        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        
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

