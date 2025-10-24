import Foundation
import SwiftUI

enum BrowserEngine: String, CaseIterable, Identifiable {
    case safari = "Safari"
    case chromium = "Chromium"
    case firefox = "Firefox"
    case tor = "Tor"
    case prism = "Prism"
    
    var id: String { rawValue }
    
    var displayName: String {
        switch self {
        case .safari: return "🔵 Safari (WebKit)"
        case .chromium: return "🔵 Chromium"
        case .firefox: return "🟠 Firefox"
        case .tor: return "🟣 Tor"
        case .prism: return "🟢 Prism"
        }
    }
    
    var badgeColor: Color {
        switch self {
        case .safari: return .blue
        case .chromium: return Color(red: 0, green: 0.48, blue: 1)
        case .firefox: return .orange
        case .tor: return .purple
        case .prism: return .green
        }
    }
}

class BrowserTab: ObservableObject, Identifiable {
    let id = UUID()
    @Published var title: String = "New Tab"
    @Published var url: URL?
    @Published var engine: BrowserEngine = .firefox
    @Published var isLoading: Bool = false
    @Published var canGoBack: Bool = false
    @Published var canGoForward: Bool = false
    
    init(url: URL? = nil, engine: BrowserEngine = .firefox) {
        self.url = url
        self.engine = engine
        if let url = url {
            self.title = url.host ?? "New Tab"
        }
    }
}

class BrowserState: ObservableObject {
    @Published var tabs: [BrowserTab] = []
    @Published var activeTab: BrowserTab?
    
    init() {
        createNewTab()
    }
    
    func createNewTab(url: URL? = nil) {
        let newTab = BrowserTab(url: url)
        tabs.append(newTab)
        activeTab = newTab
    }
    
    func closeTab(_ tab: BrowserTab) {
        guard tabs.count > 1 else { return }
        
        if let index = tabs.firstIndex(where: { $0.id == tab.id }) {
            tabs.remove(at: index)
            
            if activeTab?.id == tab.id {
                activeTab = tabs[min(index, tabs.count - 1)]
            }
        }
    }
    
    func switchToTab(_ tab: BrowserTab) {
        activeTab = tab
    }
}

