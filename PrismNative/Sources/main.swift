import SwiftUI
import AppKit

@main
struct PrismBrowserApp: App {
    @StateObject private var browserState = BrowserState()
    
    var body: some Scene {
        WindowGroup {
            ContentView()
                .environmentObject(browserState)
                .frame(minWidth: 1200, minHeight: 800)
        }
        .windowStyle(.hiddenTitleBar)
        .commands {
            CommandGroup(replacing: .newItem) {
                Button("New Tab") {
                    browserState.createNewTab()
                }
                .keyboardShortcut("t", modifiers: .command)
            }
            
            CommandGroup(after: .sidebar) {
                Menu("Engine") {
                    ForEach(BrowserEngine.allCases) { engine in
                        Button(engine.displayName) {
                            if let activeTab = browserState.activeTab {
                                activeTab.engine = engine
                            }
                        }
                    }
                }
            }
        }
    }
}

