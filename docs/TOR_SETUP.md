# 🔒 Tor Engine Setup for Prism Browser

Prism Browser supports **4 different browsing engines**, each with unique characteristics:

## 🌐 Available Engines

### 🟣 **Tor** (Default - Maximum Privacy)
- Routes traffic through the Tor network
- Anonymous browsing with IP masking
- Enhanced privacy protections
- Blocks WebRTC leaks and fingerprinting
- **Requires Tor to be installed**

### 🟠 **Firefox** 
- Firefox-like user agent and behavior
- Enhanced privacy settings
- Blocks WebRTC and canvas fingerprinting
- Isolated session storage

### 🔵 **Chromium**
- Standard Chromium engine
- Full web compatibility
- Isolated session storage

### 🟢 **Prism** (Custom Engine)
- Custom rendering via backend
- Advanced privacy features
- Local search indexing
- Custom protocol support (`prism://`)

---

## 🚀 Quick Setup (macOS)

### Install Tor

Run our automated setup script:

```bash
cd /Users/hamishleahy/Desktop/Software\ Projects/Prism
./scripts/setup-tor.sh
```

This script will:
1. ✅ Install Tor via Homebrew
2. ✅ Configure Tor for Prism Browser
3. ✅ Start Tor service on port 9050
4. ✅ Test the connection

### Manual Installation

If you prefer manual setup:

```bash
# Install Tor
brew install tor

# Start Tor service
brew services start tor
```

Or start Tor temporarily:

```bash
tor
```

---

## 🔧 Configuration

### Tor Configuration File

The setup script creates a custom Tor configuration at:
```
~/.prism/tor/torrc
```

Key settings:
- **SOCKS5 Proxy**: `localhost:9050`
- **Control Port**: `localhost:9051` (optional)
- **DNS Port**: `5353`
- **Enhanced Privacy**: Enabled
- **Exit Relay**: Disabled (client-only)

### Browser Integration

Prism Browser automatically:
- ✅ Detects Tor on `localhost:9050`
- ✅ Routes traffic through SOCKS5 proxy
- ✅ Uses Tor Browser user agent
- ✅ Blocks WebRTC IP leaks
- ✅ Spoofs timezone to UTC
- ✅ Blocks canvas fingerprinting
- ✅ Denies media/geolocation permissions
- ✅ Uses purple tab indicator 🟣

---

## 🎨 Visual Indicators

Each engine has a **unique color** in the browser:

| Engine | Badge Color | Tab Dot |
|--------|------------|---------|
| 🟣 Tor | Purple | 🟣 |
| 🟠 Firefox | Orange | 🟠 |
| 🔵 Chromium | Blue | 🔵 |
| 🟢 Prism | Green | 🟢 |

---

## 🔍 Verify Tor is Working

### Check Tor Service Status

```bash
# Check if Tor is running
pgrep -x tor

# View Tor logs
tail -f ~/.prism/tor/tor.log
```

### Test Tor Connection

Visit these URLs in Prism Browser with Tor engine:

1. **Tor Check**: https://check.torproject.org/
   - Should say "Congratulations. This browser is configured to use Tor."

2. **IP Check**: https://whatismyipaddress.com/
   - Should show a Tor exit node IP (not your real IP)

3. **Browser Fingerprint**: https://browserleaks.com/
   - Check WebRTC, Canvas, Timezone sections

---

## 🛠️ Troubleshooting

### Tor Not Connecting

**Problem**: "Connection refused" or "Proxy not available"

**Solutions**:

1. **Check if Tor is running**:
   ```bash
   pgrep -x tor
   ```

2. **Start Tor manually**:
   ```bash
   brew services start tor
   # OR
   tor -f ~/.prism/tor/torrc &
   ```

3. **Check port 9050**:
   ```bash
   lsof -i :9050
   ```

4. **View Tor logs**:
   ```bash
   tail -f ~/.prism/tor/tor.log
   ```

### Slow Connection

**Problem**: Websites load slowly with Tor

**This is normal**. Tor routes traffic through 3+ nodes for anonymity, which adds latency.

**Tips**:
- Be patient, especially for the first connection
- Try switching to a new circuit (close/reopen tab)
- Use Chromium or Firefox engines for speed

### DNS Leaks

**Problem**: Worried about DNS leaks

**Built-in Protection**:
- Prism automatically routes DNS through Tor
- SOCKS5 proxy handles DNS resolution
- No system DNS is used

**Verify**:
- Visit https://ipleak.net/ with Tor engine
- All IPs should be Tor exit nodes

---

## 🔐 Privacy Tips

### Maximum Privacy Setup

1. **Use Tor Engine** for anonymous browsing
2. **Don't login to personal accounts** while using Tor
3. **Don't install browser extensions** (fingerprinting risk)
4. **Avoid downloading files** (could reveal real IP)
5. **Use HTTPS** whenever possible
6. **Don't resize the browser window** (fingerprinting)

### When to Use Each Engine

| Use Case | Recommended Engine |
|----------|-------------------|
| Anonymous browsing | 🟣 Tor |
| Privacy-focused | 🟠 Firefox |
| General browsing | 🔵 Chromium |
| Local content | 🟢 Prism |

---

## 📊 Engine Comparison

| Feature | Tor | Firefox | Chromium | Prism |
|---------|-----|---------|----------|-------|
| Anonymity | ✅ Max | ⚠️ Medium | ❌ Low | ⚠️ Medium |
| Speed | ⚠️ Slow | ✅ Fast | ✅ Fast | ✅ Fast |
| Privacy | ✅ Max | ✅ High | ⚠️ Medium | ✅ High |
| Compatibility | ⚠️ Good | ✅ Excellent | ✅ Excellent | ⚠️ Good |
| Setup Required | ✅ Yes | ❌ No | ❌ No | ❌ No |

---

## 🎯 Per-Tab Engine Selection

Prism Browser supports **different engines per tab**!

1. Open a new tab
2. Select engine from top bar dropdown
3. Navigate as normal
4. Each tab remembers its engine

**Color-coded tabs** make it easy to see which engine each tab is using! 🎨

---

## 🆘 Support

### Report Issues

If you encounter problems:

1. Check the logs:
   ```bash
   tail -f ~/.prism/tor/tor.log
   ```

2. Test Tor directly:
   ```bash
   curl -x socks5h://localhost:9050 https://check.torproject.org/
   ```

3. Restart Tor:
   ```bash
   pkill -x tor
   brew services restart tor
   ```

### Community

- GitHub Issues: https://github.com/prism-browser/prism/issues
- Documentation: https://docs.prism-browser.com

---

## 📚 Additional Resources

- [Tor Project](https://www.torproject.org/)
- [Tor Browser Manual](https://tb-manual.torproject.org/)
- [Privacy Tools](https://www.privacytools.io/)
- [Electronic Frontier Foundation](https://www.eff.org/)

---

**🎉 Enjoy anonymous and private browsing with Prism Browser + Tor!** 🔒

