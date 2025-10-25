/**
 * Dependency Manager - Checks and manages external dependencies
 * Handles PHP backend, Tor, Firefox, Chromium, etc.
 */

const { spawn, exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const { app, shell } = require('electron');
const os = require('os');

class DependencyManager {
    constructor() {
        this.platform = process.platform;
        this.backendProcess = null;
        this.dependencies = {
            php: { installed: false, version: null, path: null, required: true },
            tor: { installed: false, version: null, path: null, required: false },
            firefox: { installed: false, version: null, path: null, required: false },
            chromium: { installed: false, version: null, path: null, required: false }
        };
    }

    async checkAll() {
        console.log('🔍 Checking dependencies...');
        
        await Promise.all([
            this.checkPHP(),
            this.checkTor(),
            this.checkFirefox(),
            this.checkChromium()
        ]);

        const report = this.generateReport();
        console.log(report);
        
        return {
            allInstalled: this.areRequiredInstalled(),
            dependencies: this.dependencies,
            report: report
        };
    }

    // ===== PHP Backend =====
    
    async checkPHP() {
        return new Promise((resolve) => {
            // Common PHP paths on macOS
            const commonPaths = [
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/homebrew/bin/php',
                '/opt/local/bin/php',
                '/Applications/MAMP/bin/php/php8.2.0/bin/php',
                '/Applications/MAMP/bin/php/php8.1.0/bin/php',
                path.join(os.homedir(), '.phpbrew/php/php-8.2.0/bin/php')
            ];

            // Try common paths first
            for (const phpPath of commonPaths) {
                if (fs.existsSync(phpPath)) {
                    // Found PHP, get version
                    exec(`"${phpPath}" -v`, (error, stdout) => {
                        if (!error && stdout) {
                            const versionMatch = stdout.match(/PHP (\d+\.\d+\.\d+)/);
                            this.dependencies.php.installed = true;
                            this.dependencies.php.version = versionMatch ? versionMatch[1] : 'unknown';
                            this.dependencies.php.path = phpPath;
                            console.log('✅ Found PHP at:', phpPath);
                            resolve(true);
                        } else {
                            resolve(false);
                        }
                    });
                    return;
                }
            }

            // Fallback: try with expanded PATH
            const expandedPath = `/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:${process.env.PATH || ''}`;
            exec('php -v', { env: { ...process.env, PATH: expandedPath } }, (error, stdout, stderr) => {
                if (!error && stdout) {
                    const versionMatch = stdout.match(/PHP (\d+\.\d+\.\d+)/);
                    this.dependencies.php.installed = true;
                    this.dependencies.php.version = versionMatch ? versionMatch[1] : 'unknown';
                    
                    // Find PHP path
                    exec('which php', { env: { ...process.env, PATH: expandedPath } }, (err, phpPath) => {
                        if (!err && phpPath) {
                            this.dependencies.php.path = phpPath.trim();
                            console.log('✅ Found PHP at:', phpPath.trim());
                        }
                        resolve(true);
                    });
                } else {
                    console.log('❌ PHP not found in common paths or PATH');
                    this.dependencies.php.installed = false;
                    resolve(false);
                }
            });
        });
    }

    async startBackend() {
        if (!this.dependencies.php.installed) {
            console.error('❌ Cannot start backend: PHP not installed');
            return { success: false, error: 'PHP not installed' };
        }

        if (this.backendProcess) {
            console.log('⚠️ Backend already running');
            return { success: true, message: 'Already running' };
        }

        try {
            // Get backend directory
            const isDev = !app.isPackaged;
            const backendDir = isDev 
                ? path.join(app.getAppPath(), '..', '..', 'backend')
                : path.join(process.resourcesPath, 'backend');

            if (!fs.existsSync(backendDir)) {
                console.error('❌ Backend directory not found:', backendDir);
                return { success: false, error: 'Backend directory not found' };
            }

            console.log('🚀 Starting PHP backend...');
            console.log('   Directory:', backendDir);
            
            // Get PHP executable path
            const phpPath = this.dependencies.php.path || 'php';
            console.log('   Using PHP:', phpPath);
            
            // Expanded PATH for finding PHP
            const expandedPath = `/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:${process.env.PATH || ''}`;
            
            // Start PHP built-in server
            this.backendProcess = spawn(phpPath, [
                '-S', 'localhost:8000',
                '-t', 'public',
                'public/index.php'
            ], {
                cwd: backendDir,
                stdio: ['ignore', 'pipe', 'pipe'],
                env: { ...process.env, PATH: expandedPath }
            });

            this.backendProcess.stdout.on('data', (data) => {
                console.log(`[Backend] ${data.toString().trim()}`);
            });

            this.backendProcess.stderr.on('data', (data) => {
                const message = data.toString().trim();
                // PHP uses stderr for startup messages, don't treat as error
                if (message.includes('Development Server') || message.includes('started')) {
                    console.log(`[Backend] ${message}`);
                } else if (message.includes('error') || message.includes('Error')) {
                    console.error(`[Backend Error] ${message}`);
                }
            });

            this.backendProcess.on('close', (code) => {
                console.log(`[Backend] Process exited with code ${code}`);
                this.backendProcess = null;
            });

            this.backendProcess.on('error', (error) => {
                console.error('[Backend] Failed to start:', error);
                this.backendProcess = null;
            });

            // Wait a bit to ensure it started
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Test if backend is responding
            const isRunning = await this.testBackendConnection();
            
            if (isRunning) {
                console.log('✅ Backend started successfully on http://localhost:8000');
                return { success: true, url: 'http://localhost:8000' };
            } else {
                console.warn('⚠️ Backend started but not responding yet');
                return { success: true, url: 'http://localhost:8000', warning: 'Starting...' };
            }

        } catch (error) {
            console.error('❌ Failed to start backend:', error);
            return { success: false, error: error.message };
        }
    }

    async testBackendConnection() {
        try {
            const response = await fetch('http://localhost:8000/health');
            return response.ok;
        } catch (error) {
            return false;
        }
    }

    stopBackend() {
        if (this.backendProcess) {
            console.log('🛑 Stopping backend...');
            this.backendProcess.kill();
            this.backendProcess = null;
        }
    }

    // ===== Tor =====
    
    async checkTor() {
        return new Promise((resolve) => {
            // Common Tor paths on macOS
            const commonPaths = [
                '/usr/local/bin/tor',
                '/opt/homebrew/bin/tor',
                '/usr/bin/tor',
                '/opt/local/bin/tor',
                path.join(os.homedir(), '.local/bin/tor')
            ];

            // Try common paths first
            for (const torPath of commonPaths) {
                if (fs.existsSync(torPath)) {
                    // Found Tor, get version
                    exec(`"${torPath}" --version`, (error, stdout, stderr) => {
                        if (!error || stdout || stderr) {
                            const output = stdout || stderr || '';
                            const versionMatch = output.match(/Tor version (\d+\.\d+\.\d+\.\d+)/);
                            this.dependencies.tor.installed = true;
                            this.dependencies.tor.version = versionMatch ? versionMatch[1] : 'unknown';
                            this.dependencies.tor.path = torPath;
                            console.log('✅ Found Tor at:', torPath);
                            resolve(true);
                        } else {
                            resolve(false);
                        }
                    });
                    return;
                }
            }

            // Fallback: try with expanded PATH
            const expandedPath = `/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:${process.env.PATH || ''}`;
            exec('tor --version', { env: { ...process.env, PATH: expandedPath } }, (error, stdout, stderr) => {
                if (!error || stdout || stderr) {
                    const output = stdout || stderr || '';
                    const versionMatch = output.match(/Tor version (\d+\.\d+\.\d+\.\d+)/);
                    this.dependencies.tor.installed = true;
                    this.dependencies.tor.version = versionMatch ? versionMatch[1] : 'unknown';
                    
                    exec('which tor', { env: { ...process.env, PATH: expandedPath } }, (err, torPath) => {
                        if (!err && torPath) {
                            this.dependencies.tor.path = torPath.trim();
                            console.log('✅ Found Tor at:', torPath.trim());
                        }
                        resolve(true);
                    });
                } else {
                    console.log('❌ Tor not found in common paths or PATH');
                    this.dependencies.tor.installed = false;
                    resolve(false);
                }
            });
        });
    }

    // ===== Firefox =====
    
    async checkFirefox() {
        return new Promise((resolve) => {
            const paths = this.getFirefoxPaths();
            
            for (const firefoxPath of paths) {
                if (fs.existsSync(firefoxPath)) {
                    this.dependencies.firefox.installed = true;
                    this.dependencies.firefox.path = firefoxPath;
                    
                    // Try to get version
                    const versionCmd = this.platform === 'darwin' 
                        ? `${firefoxPath} --version 2>/dev/null || defaults read ${firefoxPath.replace('/MacOS/firefox', '')}/Info.plist CFBundleShortVersionString`
                        : `${firefoxPath} --version`;
                    
                    exec(versionCmd, (error, stdout) => {
                        if (!error && stdout) {
                            const versionMatch = stdout.match(/(\d+\.\d+)/);
                            this.dependencies.firefox.version = versionMatch ? versionMatch[1] : 'installed';
                        }
                        resolve(true);
                    });
                    return;
                }
            }
            
            this.dependencies.firefox.installed = false;
            resolve(false);
        });
    }

    getFirefoxPaths() {
        switch (this.platform) {
            case 'darwin':
                return [
                    '/Applications/Firefox.app/Contents/MacOS/firefox',
                    '/Applications/Firefox Developer Edition.app/Contents/MacOS/firefox',
                    path.join(os.homedir(), 'Applications/Firefox.app/Contents/MacOS/firefox')
                ];
            case 'win32':
                return [
                    'C:\\Program Files\\Mozilla Firefox\\firefox.exe',
                    'C:\\Program Files (x86)\\Mozilla Firefox\\firefox.exe',
                    path.join(os.homedir(), 'AppData\\Local\\Mozilla Firefox\\firefox.exe')
                ];
            default: // linux
                return [
                    '/usr/bin/firefox',
                    '/usr/local/bin/firefox',
                    '/snap/bin/firefox',
                    '/opt/firefox/firefox'
                ];
        }
    }

    // ===== Chromium =====
    
    async checkChromium() {
        return new Promise((resolve) => {
            // Chromium is built into Electron, so it's always available
            this.dependencies.chromium.installed = true;
            this.dependencies.chromium.version = process.versions.chrome;
            this.dependencies.chromium.path = 'Built-in (Electron)';
            resolve(true);
        });
    }

    // ===== Helpers =====
    
    areRequiredInstalled() {
        return Object.entries(this.dependencies)
            .filter(([key, dep]) => dep.required)
            .every(([key, dep]) => dep.installed);
    }

    generateReport() {
        let report = '\n📋 Dependency Report:\n';
        report += '═══════════════════════════════════════\n';
        
        for (const [name, dep] of Object.entries(this.dependencies)) {
            const status = dep.installed ? '✅' : '❌';
            const required = dep.required ? '[REQUIRED]' : '[OPTIONAL]';
            const version = dep.version ? `v${dep.version}` : '';
            
            report += `${status} ${name.toUpperCase()} ${required} ${version}\n`;
            if (dep.path) {
                report += `   Path: ${dep.path}\n`;
            }
            if (!dep.installed && dep.required) {
                report += `   ⚠️  Installation required!\n`;
            }
        }
        
        report += '═══════════════════════════════════════\n';
        return report;
    }

    getInstallInstructions(dependency) {
        const instructions = {
            php: {
                darwin: 'Install via Homebrew:\nbrew install php',
                win32: 'Download from: https://windows.php.net/download',
                linux: 'Install via package manager:\nsudo apt install php\nor\nsudo yum install php'
            },
            tor: {
                darwin: 'Install via Homebrew:\nbrew install tor\n\nThen run:\nbrew services start tor',
                win32: 'Download Tor Browser from:\nhttps://www.torproject.org/download/',
                linux: 'Install via package manager:\nsudo apt install tor\n\nThen start:\nsudo systemctl start tor'
            },
            firefox: {
                darwin: 'Download from:\nhttps://www.mozilla.org/firefox/download/',
                win32: 'Download from:\nhttps://www.mozilla.org/firefox/download/',
                linux: 'Install via package manager:\nsudo apt install firefox\nor download from:\nhttps://www.mozilla.org/firefox/download/'
            }
        };

        return instructions[dependency]?.[this.platform] || 'Please visit the official website to download.';
    }

    openInstallPage(dependency) {
        const urls = {
            php: {
                darwin: 'https://brew.sh',
                win32: 'https://windows.php.net/download',
                linux: 'https://www.php.net/downloads'
            },
            tor: {
                darwin: 'https://brew.sh',
                win32: 'https://www.torproject.org/download/',
                linux: 'https://www.torproject.org/download/'
            },
            firefox: 'https://www.mozilla.org/firefox/download/'
        };

        const url = typeof urls[dependency] === 'object' 
            ? urls[dependency][this.platform]
            : urls[dependency];

        if (url) {
            shell.openExternal(url);
        }
    }

    // ===== Status =====
    
    getStatus() {
        return {
            php: this.dependencies.php,
            tor: this.dependencies.tor,
            firefox: this.dependencies.firefox,
            chromium: this.dependencies.chromium,
            backendRunning: this.backendProcess !== null,
            allRequired: this.areRequiredInstalled()
        };
    }
}

module.exports = DependencyManager;

