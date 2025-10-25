/**
 * DataManager - Local data persistence for Prism Browser
 * Handles user accounts, passwords, history, and session data
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { app } = require('electron');

class DataManager {
    constructor() {
        // Get user data directory
        this.dataDir = path.join(app.getPath('userData'), 'prism-data');
        this.usersFile = path.join(this.dataDir, 'users.json');
        this.historyFile = path.join(this.dataDir, 'history.json');
        this.passwordsFile = path.join(this.dataDir, 'passwords.json');
        this.sessionsFile = path.join(this.dataDir, 'sessions.json');
        
        // Encryption key (in production, derive from user password)
        this.encryptionKey = null;
        
        // Current session
        this.currentUser = null;
        
        // Initialize
        this.initialize();
    }

    initialize() {
        // Create data directory if it doesn't exist
        if (!fs.existsSync(this.dataDir)) {
            fs.mkdirSync(this.dataDir, { recursive: true });
            console.log('📁 Created data directory:', this.dataDir);
        }

        // Initialize data files
        this.initializeFile(this.usersFile, []);
        this.initializeFile(this.historyFile, []);
        this.initializeFile(this.passwordsFile, []);
        this.initializeFile(this.sessionsFile, {});

        // Try to restore last session
        this.restoreSession();
    }

    initializeFile(filePath, defaultData) {
        if (!fs.existsSync(filePath)) {
            fs.writeFileSync(filePath, JSON.stringify(defaultData, null, 2));
            console.log('✅ Initialized:', path.basename(filePath));
        }
    }

    // ===== USER MANAGEMENT =====

    createUser(username, password) {
        try {
            const users = this.getUsers();
            
            // Check if user already exists
            if (users.find(u => u.username === username)) {
                throw new Error('Username already exists');
            }

            // Create user with hashed password
            const salt = crypto.randomBytes(16).toString('hex');
            const hash = this.hashPassword(password, salt);
            
            const user = {
                id: crypto.randomUUID(),
                username: username,
                passwordHash: hash,
                salt: salt,
                createdAt: new Date().toISOString(),
                lastLogin: null
            };

            users.push(user);
            fs.writeFileSync(this.usersFile, JSON.stringify(users, null, 2));
            
            console.log('✅ User created:', username);
            return { success: true, user: { id: user.id, username: user.username } };
        } catch (error) {
            console.error('❌ Failed to create user:', error);
            return { success: false, error: error.message };
        }
    }

    loginUser(username, password) {
        try {
            const users = this.getUsers();
            const user = users.find(u => u.username === username);
            
            if (!user) {
                throw new Error('User not found');
            }

            // Verify password
            const hash = this.hashPassword(password, user.salt);
            if (hash !== user.passwordHash) {
                throw new Error('Invalid password');
            }

            // Update last login
            user.lastLogin = new Date().toISOString();
            fs.writeFileSync(this.usersFile, JSON.stringify(users, null, 2));

            // Set current user
            this.currentUser = { id: user.id, username: user.username };
            
            // Generate encryption key from password
            this.encryptionKey = crypto.scryptSync(password, user.salt, 32);

            // Save session
            this.saveSession(user.id);

            console.log('✅ User logged in:', username);
            return { success: true, user: this.currentUser };
        } catch (error) {
            console.error('❌ Login failed:', error);
            return { success: false, error: error.message };
        }
    }

    logoutUser() {
        this.currentUser = null;
        this.encryptionKey = null;
        this.clearSession();
        console.log('✅ User logged out');
    }

    getCurrentUser() {
        return this.currentUser;
    }

    getUsers() {
        try {
            const data = fs.readFileSync(this.usersFile, 'utf8');
            return JSON.parse(data);
        } catch (error) {
            return [];
        }
    }

    hashPassword(password, salt) {
        return crypto.pbkdf2Sync(password, salt, 100000, 64, 'sha512').toString('hex');
    }

    // ===== SESSION MANAGEMENT =====

    saveSession(userId) {
        const session = {
            userId: userId,
            timestamp: new Date().toISOString()
        };
        fs.writeFileSync(this.sessionsFile, JSON.stringify(session, null, 2));
    }

    restoreSession() {
        try {
            if (fs.existsSync(this.sessionsFile)) {
                const session = JSON.parse(fs.readFileSync(this.sessionsFile, 'utf8'));
                if (session.userId) {
                    const users = this.getUsers();
                    const user = users.find(u => u.id === session.userId);
                    if (user) {
                        // Note: User needs to re-enter password for full encryption
                        this.currentUser = { id: user.id, username: user.username };
                        console.log('✅ Session restored:', user.username);
                        return true;
                    }
                }
            }
        } catch (error) {
            console.error('⚠️ Failed to restore session:', error);
        }
        return false;
    }

    clearSession() {
        fs.writeFileSync(this.sessionsFile, JSON.stringify({}, null, 2));
    }

    // ===== PASSWORD VAULT =====

    savePassword(domain, username, password) {
        if (!this.currentUser || !this.encryptionKey) {
            return { success: false, error: 'User not logged in' };
        }

        try {
            const passwords = this.getPasswords();
            
            // Encrypt password
            const encrypted = this.encryptData(password);
            
            const entry = {
                id: crypto.randomUUID(),
                userId: this.currentUser.id,
                domain: domain,
                username: username,
                password: encrypted,
                createdAt: new Date().toISOString(),
                lastUsed: new Date().toISOString()
            };

            // Check if entry exists, update or create
            const existingIndex = passwords.findIndex(
                p => p.userId === this.currentUser.id && p.domain === domain && p.username === username
            );

            if (existingIndex >= 0) {
                passwords[existingIndex] = { ...passwords[existingIndex], ...entry };
            } else {
                passwords.push(entry);
            }

            fs.writeFileSync(this.passwordsFile, JSON.stringify(passwords, null, 2));
            console.log('✅ Password saved for:', domain);
            return { success: true };
        } catch (error) {
            console.error('❌ Failed to save password:', error);
            return { success: false, error: error.message };
        }
    }

    getPassword(domain, username) {
        if (!this.currentUser || !this.encryptionKey) {
            return null;
        }

        try {
            const passwords = this.getPasswords();
            const entry = passwords.find(
                p => p.userId === this.currentUser.id && p.domain === domain && p.username === username
            );

            if (entry) {
                // Decrypt password
                const decrypted = this.decryptData(entry.password);
                
                // Update last used
                entry.lastUsed = new Date().toISOString();
                fs.writeFileSync(this.passwordsFile, JSON.stringify(passwords, null, 2));
                
                return decrypted;
            }
        } catch (error) {
            console.error('❌ Failed to get password:', error);
        }
        return null;
    }

    getAllPasswords() {
        if (!this.currentUser) {
            return [];
        }

        const passwords = this.getPasswords();
        return passwords
            .filter(p => p.userId === this.currentUser.id)
            .map(p => ({
                id: p.id,
                domain: p.domain,
                username: p.username,
                createdAt: p.createdAt,
                lastUsed: p.lastUsed
            }));
    }

    deletePassword(id) {
        if (!this.currentUser) {
            return { success: false, error: 'User not logged in' };
        }

        try {
            let passwords = this.getPasswords();
            passwords = passwords.filter(p => !(p.id === id && p.userId === this.currentUser.id));
            fs.writeFileSync(this.passwordsFile, JSON.stringify(passwords, null, 2));
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    getPasswords() {
        try {
            const data = fs.readFileSync(this.passwordsFile, 'utf8');
            return JSON.parse(data);
        } catch (error) {
            return [];
        }
    }

    // ===== BROWSING HISTORY =====

    addToHistory(url, title, engine) {
        if (!this.currentUser) {
            return; // Don't track history if not logged in
        }

        try {
            const history = this.getHistory();
            
            const entry = {
                id: crypto.randomUUID(),
                userId: this.currentUser.id,
                url: url,
                title: title || url,
                engine: engine,
                timestamp: new Date().toISOString(),
                visitCount: 1
            };

            // Check if URL exists for this user
            const existingIndex = history.findIndex(
                h => h.userId === this.currentUser.id && h.url === url
            );

            if (existingIndex >= 0) {
                // Update existing entry
                history[existingIndex].visitCount++;
                history[existingIndex].timestamp = new Date().toISOString();
                history[existingIndex].title = title || history[existingIndex].title;
            } else {
                // Add new entry
                history.unshift(entry); // Add to beginning
            }

            // Keep only last 10,000 entries
            const userHistory = history.filter(h => h.userId === this.currentUser.id);
            if (userHistory.length > 10000) {
                // Remove oldest entries for this user
                const entriesToRemove = userHistory.slice(10000);
                const idsToRemove = new Set(entriesToRemove.map(e => e.id));
                const filtered = history.filter(h => !idsToRemove.has(h.id));
                fs.writeFileSync(this.historyFile, JSON.stringify(filtered, null, 2));
            } else {
                fs.writeFileSync(this.historyFile, JSON.stringify(history, null, 2));
            }
        } catch (error) {
            console.error('❌ Failed to add to history:', error);
        }
    }

    getHistory(limit = 100, searchQuery = '') {
        if (!this.currentUser) {
            return [];
        }

        try {
            const history = this.getHistoryData();
            let userHistory = history.filter(h => h.userId === this.currentUser.id);

            // Search if query provided
            if (searchQuery) {
                const query = searchQuery.toLowerCase();
                userHistory = userHistory.filter(h => 
                    h.title.toLowerCase().includes(query) || 
                    h.url.toLowerCase().includes(query)
                );
            }

            // Sort by timestamp (newest first) and limit
            return userHistory
                .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
                .slice(0, limit);
        } catch (error) {
            console.error('❌ Failed to get history:', error);
            return [];
        }
    }

    clearHistory() {
        if (!this.currentUser) {
            return { success: false, error: 'User not logged in' };
        }

        try {
            let history = this.getHistoryData();
            // Remove only current user's history
            history = history.filter(h => h.userId !== this.currentUser.id);
            fs.writeFileSync(this.historyFile, JSON.stringify(history, null, 2));
            console.log('✅ History cleared');
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    getHistoryData() {
        try {
            const data = fs.readFileSync(this.historyFile, 'utf8');
            return JSON.parse(data);
        } catch (error) {
            return [];
        }
    }

    // ===== ENCRYPTION =====

    encryptData(text) {
        if (!this.encryptionKey) {
            throw new Error('No encryption key available');
        }

        const iv = crypto.randomBytes(16);
        const cipher = crypto.createCipheriv('aes-256-cbc', this.encryptionKey, iv);
        let encrypted = cipher.update(text, 'utf8', 'hex');
        encrypted += cipher.final('hex');
        
        return {
            iv: iv.toString('hex'),
            data: encrypted
        };
    }

    decryptData(encrypted) {
        if (!this.encryptionKey) {
            throw new Error('No encryption key available');
        }

        const iv = Buffer.from(encrypted.iv, 'hex');
        const decipher = crypto.createDecipheriv('aes-256-cbc', this.encryptionKey, iv);
        let decrypted = decipher.update(encrypted.data, 'hex', 'utf8');
        decrypted += decipher.final('utf8');
        
        return decrypted;
    }

    // ===== STATS =====

    getStats() {
        return {
            totalUsers: this.getUsers().length,
            currentUser: this.currentUser,
            historyEntries: this.currentUser ? this.getHistory(999999).length : 0,
            savedPasswords: this.currentUser ? this.getAllPasswords().length : 0
        };
    }
}

module.exports = DataManager;

