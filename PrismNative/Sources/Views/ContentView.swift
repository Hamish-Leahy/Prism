import SwiftUI
import WebKit

struct ContentView: View {
    @EnvironmentObject var browserState: BrowserState
    @State private var addressBarText: String = ""
    
    var body: some View {
        VStack(spacing: 0) {
            // Top Bar
            TopBar(addressBarText: $addressBarText)
                .background(Color.white)
                .shadow(color: Color.black.opacity(0.04), radius: 2, x: 0, y: 1)
            
            // Tab Bar
            TabBar()
                .background(Color(red: 0.98, green: 0.98, blue: 0.99))
            
            Divider()
            
            // Web Content
            WebContentView()
        }
        .background(Color.white)
        .onAppear {
            if let url = browserState.activeTab?.url {
                addressBarText = url.absoluteString
            }
        }
        .onChange(of: browserState.activeTab?.url) { newValue in
            if let url = newValue {
                addressBarText = url.absoluteString
            } else {
                addressBarText = ""
            }
        }
    }
}

struct TopBar: View {
    @EnvironmentObject var browserState: BrowserState
    @Binding var addressBarText: String
    @State private var isAddressBarFocused = false
    
    var body: some View {
        HStack(spacing: 14) {
            // Navigation buttons - clean and responsive
            HStack(spacing: 8) {
                Button(action: { goBack() }) {
                    Image(systemName: "chevron.left")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.primary)
                }
                .disabled(!(browserState.activeTab?.canGoBack ?? false))
                
                Button(action: { goForward() }) {
                    Image(systemName: "chevron.right")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.primary)
                }
                .disabled(!(browserState.activeTab?.canGoForward ?? false))
                
                Button(action: { reload() }) {
                    Image(systemName: "arrow.clockwise")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundColor(.primary)
                }
            }
            .buttonStyle(ChromeButtonStyle())
            
            // Clean address bar with clear borders
            HStack(spacing: 12) {
                if let tab = browserState.activeTab {
                    // Engine indicator
                    Circle()
                        .fill(tab.engine.badgeColor)
                        .frame(width: 10, height: 10)
                }
                
                TextField("Search or enter address", text: $addressBarText)
                    .textFieldStyle(.plain)
                    .font(.system(size: 14))
                    .onSubmit {
                        navigateTo(addressBarText)
                    }
                
                // Engine selector
                if let tab = browserState.activeTab {
                    Menu {
                        ForEach(BrowserEngine.allCases) { engine in
                            Button(action: {
                                tab.engine = engine
                            }) {
                                HStack {
                                    Circle()
                                        .fill(engine.badgeColor)
                                        .frame(width: 10, height: 10)
                                    Text(engine.rawValue)
                                    Spacer()
                                    if tab.engine == engine {
                                        Image(systemName: "checkmark")
                                            .font(.system(size: 12, weight: .bold))
                                    }
                                }
                            }
                        }
                    } label: {
                        Image(systemName: "chevron.down.circle.fill")
                            .font(.system(size: 16))
                            .foregroundColor(.secondary)
                    }
                    .menuStyle(.borderlessButton)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(Color.white)
            .cornerRadius(12)
            .overlay(
                RoundedRectangle(cornerRadius: 12)
                    .stroke(Color.gray.opacity(0.25), lineWidth: 1.5)
            )
            .shadow(color: Color.black.opacity(0.03), radius: 2, x: 0, y: 1)
        }
        .padding(.horizontal, 80)
        .padding(.vertical, 12)
    }
    
    func goBack() {
        // Implement in WebContentView
    }
    
    func goForward() {
        // Implement in WebContentView
    }
    
    func reload() {
        // Implement in WebContentView
    }
    
    func navigateTo(_ input: String) {
        guard let tab = browserState.activeTab else { return }
        
        var urlString = input.trimmingCharacters(in: .whitespaces)
        
        // Check if it's a URL or search query
        if !urlString.contains(".") || urlString.contains(" ") {
            // It's a search query
            urlString = "https://www.google.com/search?q=" + urlString.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed)!
        } else if !urlString.hasPrefix("http://") && !urlString.hasPrefix("https://") {
            urlString = "https://" + urlString
        }
        
        if let url = URL(string: urlString) {
            tab.url = url
        }
    }
}

struct TabBar: View {
    @EnvironmentObject var browserState: BrowserState
    
    var body: some View {
        HStack(spacing: 2) {
            ForEach(browserState.tabs) { tab in
                TabView(tab: tab)
            }
            
            // New tab button - Chrome style
            Button(action: {
                browserState.createNewTab()
            }) {
                Image(systemName: "plus")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundColor(.primary)
            }
            .buttonStyle(PlainButtonStyle())
            .frame(width: 32, height: 32)
            .background(Color.white)
            .cornerRadius(8)
            .overlay(
                RoundedRectangle(cornerRadius: 8)
                    .stroke(Color.gray.opacity(0.2), lineWidth: 1)
            )
            .padding(.leading, 4)
            
            Spacer()
        }
        .padding(.horizontal, 80)
        .padding(.vertical, 10)
    }
}

struct TabView: View {
    @EnvironmentObject var browserState: BrowserState
    @ObservedObject var tab: BrowserTab
    @State private var isHovering = false
    
    var isActive: Bool {
        browserState.activeTab?.id == tab.id
    }
    
    var body: some View {
        HStack(spacing: 10) {
            // Engine dot
            Circle()
                .fill(tab.engine.badgeColor)
                .frame(width: 8, height: 8)
            
            Text(tab.title)
                .font(.system(size: 13, weight: isActive ? .semibold : .regular))
                .lineLimit(1)
                .frame(maxWidth: 160)
                .foregroundColor(.primary)
            
            Spacer()
            
            if isHovering || browserState.tabs.count > 1 {
                Button(action: {
                    withAnimation(.easeInOut(duration: 0.2)) {
                        browserState.closeTab(tab)
                    }
                }) {
                    Image(systemName: "xmark")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundColor(.secondary)
                }
                .buttonStyle(PlainButtonStyle())
                .frame(width: 18, height: 18)
                .background(Color.gray.opacity(isHovering ? 0.15 : 0.08))
                .cornerRadius(4)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minWidth: 140, maxWidth: 220)
        .background(isActive ? Color.white : Color.white.opacity(0.7))
        .cornerRadius(10, corners: [.topLeft, .topRight])
        .overlay(
            RoundedRectangle(cornerRadius: 10)
                .stroke(isActive ? Color.gray.opacity(0.3) : Color.gray.opacity(0.15), lineWidth: 1.5)
        )
        .shadow(color: isActive ? Color.black.opacity(0.05) : Color.clear, radius: 4, x: 0, y: 2)
        .onHover { hovering in
            withAnimation(.easeInOut(duration: 0.2)) {
                isHovering = hovering
            }
        }
        .onTapGesture {
            withAnimation(.easeInOut(duration: 0.2)) {
                browserState.switchToTab(tab)
            }
        }
    }
}

struct WebContentView: View {
    @EnvironmentObject var browserState: BrowserState
    
    var body: some View {
        ZStack {
            if let tab = browserState.activeTab {
                if let url = tab.url {
                    switch tab.engine {
                    case .safari:
                        SafariWebView(tab: tab)
                    case .firefox:
                        FirefoxWebView(tab: tab)
                    case .tor:
                        TorWebView(tab: tab)
                    case .prism:
                        PrismWebView(tab: tab)
                    case .chromium:
                        ChromiumWebView(tab: tab)
                    }
                } else {
                    StartPage()
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

struct StartPage: View {
    @EnvironmentObject var browserState: BrowserState
    @State private var searchText = ""
    
    var body: some View {
        ZStack {
            // Clean white background
            Color.white
            
            VStack(spacing: 50) {
                Spacer()
                
                // Logo and branding
                VStack(spacing: 20) {
                    Text("🔮")
                        .font(.system(size: 70))
                    
                    Text("Prism Browser")
                        .font(.system(size: 38, weight: .bold, design: .rounded))
                        .foregroundStyle(
                            LinearGradient(
                                colors: [Color.blue, Color.purple],
                                startPoint: .leading,
                                endPoint: .trailing
                            )
                        )
                    
                    Text("Multi-Engine Browsing")
                        .font(.system(size: 15))
                        .foregroundColor(.secondary)
                        .fontWeight(.medium)
                }
                
                // Large, prominent search bar
                HStack(spacing: 14) {
                    Image(systemName: "magnifyingglass")
                        .font(.system(size: 16, weight: .medium))
                        .foregroundColor(.secondary)
                    
                    TextField("Search or enter address", text: $searchText)
                        .textFieldStyle(.plain)
                        .font(.system(size: 15))
                        .onSubmit {
                            if !searchText.isEmpty {
                                navigateToSearch(searchText)
                            }
                        }
                }
                .padding(.horizontal, 20)
                .padding(.vertical, 16)
                .background(Color.white)
                .cornerRadius(14)
                .overlay(
                    RoundedRectangle(cornerRadius: 14)
                        .stroke(Color.gray.opacity(0.25), lineWidth: 1.5)
                )
                .frame(maxWidth: 650)
                .shadow(color: Color.black.opacity(0.06), radius: 12, x: 0, y: 4)
                
                // Quick links with clear borders
                VStack(spacing: 20) {
                    Text("QUICK ACCESS")
                        .font(.system(size: 11, weight: .bold))
                        .foregroundColor(.secondary)
                        .tracking(1.2)
                    
                    HStack(spacing: 16) {
                        QuickLink(title: "GitHub", icon: "chevron.left.forwardslash.chevron.right", url: "https://github.com")
                        QuickLink(title: "YouTube", icon: "play.circle.fill", url: "https://youtube.com")
                        QuickLink(title: "Reddit", icon: "bubble.left.and.bubble.right.fill", url: "https://reddit.com")
                        QuickLink(title: "Twitter", icon: "bird.fill", url: "https://twitter.com")
                    }
                }
                .padding(.top, 10)
                
                Spacer()
                Spacer()
            }
            .padding(50)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
    
    func navigateToSearch(_ query: String) {
        let urlString = "https://www.google.com/search?q=" + query.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed)!
        if let url = URL(string: urlString) {
            browserState.activeTab?.url = url
        }
    }
}

struct QuickLink: View {
    let title: String
    let icon: String
    let url: String
    @EnvironmentObject var browserState: BrowserState
    @State private var isHovering = false
    
    var body: some View {
        Button(action: {
            if let url = URL(string: url) {
                browserState.activeTab?.url = url
            }
        }) {
            VStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 24, weight: .medium))
                    .foregroundColor(.primary)
                Text(title)
                    .font(.system(size: 12, weight: .semibold))
                    .foregroundColor(.primary)
            }
            .frame(width: 110, height: 100)
            .background(Color.white)
            .cornerRadius(14)
            .overlay(
                RoundedRectangle(cornerRadius: 14)
                    .stroke(Color.gray.opacity(isHovering ? 0.35 : 0.2), lineWidth: 1.5)
            )
            .scaleEffect(isHovering ? 1.05 : 1.0)
            .shadow(color: Color.black.opacity(isHovering ? 0.1 : 0.04), radius: isHovering ? 16 : 6, x: 0, y: isHovering ? 6 : 3)
        }
        .buttonStyle(PlainButtonStyle())
        .onHover { hovering in
            withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                isHovering = hovering
            }
        }
    }
}

struct ChromeButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .frame(width: 32, height: 32)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(configuration.isPressed ? Color.gray.opacity(0.15) : Color.gray.opacity(0.05))
            )
            .scaleEffect(configuration.isPressed ? 0.95 : 1.0)
            .animation(.easeInOut(duration: 0.15), value: configuration.isPressed)
    }
}

// Helper for rounded corners
extension View {
    func cornerRadius(_ radius: CGFloat, corners: RectCorner) -> some View {
        clipShape(RoundedCorner(radius: radius, corners: corners))
    }
}

struct RectCorner: OptionSet {
    let rawValue: Int
    static let topLeft = RectCorner(rawValue: 1 << 0)
    static let topRight = RectCorner(rawValue: 1 << 1)
    static let bottomLeft = RectCorner(rawValue: 1 << 2)
    static let bottomRight = RectCorner(rawValue: 1 << 3)
    static let allCorners: RectCorner = [.topLeft, .topRight, .bottomLeft, .bottomRight]
}

struct RoundedCorner: Shape {
    var radius: CGFloat = .infinity
    var corners: RectCorner = .allCorners
    
    func path(in rect: CGRect) -> Path {
        var path = Path()
        
        let tl = corners.contains(.topLeft)
        let tr = corners.contains(.topRight)
        let bl = corners.contains(.bottomLeft)
        let br = corners.contains(.bottomRight)
        
        path.move(to: CGPoint(x: rect.minX + (tl ? radius : 0), y: rect.minY))
        path.addLine(to: CGPoint(x: rect.maxX - (tr ? radius : 0), y: rect.minY))
        if tr { path.addArc(center: CGPoint(x: rect.maxX - radius, y: rect.minY + radius), radius: radius, startAngle: .degrees(-90), endAngle: .degrees(0), clockwise: false) }
        path.addLine(to: CGPoint(x: rect.maxX, y: rect.maxY - (br ? radius : 0)))
        if br { path.addArc(center: CGPoint(x: rect.maxX - radius, y: rect.maxY - radius), radius: radius, startAngle: .degrees(0), endAngle: .degrees(90), clockwise: false) }
        path.addLine(to: CGPoint(x: rect.minX + (bl ? radius : 0), y: rect.maxY))
        if bl { path.addArc(center: CGPoint(x: rect.minX + radius, y: rect.maxY - radius), radius: radius, startAngle: .degrees(90), endAngle: .degrees(180), clockwise: false) }
        path.addLine(to: CGPoint(x: rect.minX, y: rect.minY + (tl ? radius : 0)))
        if tl { path.addArc(center: CGPoint(x: rect.minX + radius, y: rect.minY + radius), radius: radius, startAngle: .degrees(180), endAngle: .degrees(270), clockwise: false) }
        path.closeSubpath()
        
        return path
    }
}

