//
//  PrismBrowserApp.swift
//  Prism Browser - Native macOS Multi-Engine Browser
//
//  A revolutionary browser with true multi-engine support
//

import SwiftUI
import WebKit

@main
struct PrismBrowserApp: App {
    @NSApplicationDelegateAdaptor(AppDelegate.self) var appDelegate
    @StateObject private var browserState = BrowserState()
    
    var body: some Scene {
        WindowGroup {
            ContentView()
                .environmentObject(browserState)
                .frame(minWidth: 1200, minHeight: 800)
        }
        .commands {
            // File Menu
            CommandGroup(replacing: .newItem) {
                Button("New Tab") {
                    browserState.createNewTab()
                }
                .keyboardShortcut("t", modifiers: .command)
                
                Button("New Window") {
                    // Create new window
                }
                .keyboardShortcut("n", modifiers: .command)
            }
            
            // Navigation Menu
            CommandMenu("Navigate") {
                Button("Back") {
                    browserState.goBack()
                }
                .keyboardShortcut("[", modifiers: .command)
                
                Button("Forward") {
                    browserState.goForward()
                }
                .keyboardShortcut("]", modifiers: .command)
                
                Button("Reload") {
                    browserState.reload()
                }
                .keyboardShortcut("r", modifiers: .command)
                
                Divider()
                
                Button("Home") {
                    browserState.goHome()
                }
                .keyboardShortcut("h", modifiers: [.command, .shift])
            }
            
            // Engine Menu
            CommandMenu("Engine") {
                Button("Safari (WebKit)") {
                    browserState.switchEngine(.safari)
                }
                
                Button("Chromium") {
                    browserState.switchEngine(.chromium)
                }
                
                Button("Firefox") {
                    browserState.switchEngine(.firefox)
                }
                
                Button("Tor") {
                    browserState.switchEngine(.tor)
                }
            }
        }
    }
}

class AppDelegate: NSObject, NSApplicationDelegate {
    func applicationDidFinishLaunching(_ notification: Notification) {
        print("🚀 Prism Browser - Native macOS App")
        print("✅ Multi-Engine Architecture Initialized")
    }
    
    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool {
        return true
    }
}

