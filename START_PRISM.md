# 🚀 Starting Prism Browser

## Quick Start (2 Steps)

### Step 1: Start the Backend (Required)
Open Terminal and run:
```bash
cd ~/Desktop/Software\ Projects/Prism/backend
./start-backend.sh
```
**Keep this terminal window open!** The backend must be running for Prism to work.

### Step 2: Launch Prism Browser
- Open **Prism Browser** from Applications folder
- Or double-click the app icon
- Or open from Spotlight (⌘+Space, type "Prism")

---

## ⚠️ Important Notes

1. **Backend Must Run First**
   - The backend server provides search and API services
   - Without it, Prism Engine won't work (other engines will still work)
   - You'll see `http://localhost:8000` running

2. **Backend Terminal**
   - Keep the terminal window open while using Prism
   - Press `Ctrl+C` in the terminal to stop the backend
   - Backend automatically restarts if you run the script again

3. **First Launch**
   - May take a moment to initialize all 4 engines
   - Extensions will attempt to auto-install (needs internet)

---

## 🔄 Rebuilding After Changes

If you make code changes and want to rebuild:
```bash
cd ~/Desktop/Software\ Projects/Prism
./scripts/build-and-install.sh
```

This will:
- Build the latest version
- Install to Applications
- Apply the black square icon
- Refresh your Dock

---

## 🐛 Troubleshooting

**App won't open / crashes immediately:**
- Make sure backend is running first
- Check terminal for error messages

**Prism Engine doesn't work:**
- Backend must be running on port 8000
- Check `http://localhost:8000/api/health` in a browser

**Extensions not installing:**
- Check your internet connection
- Extensions auto-install on first launch (optional)

---

## 📝 Version Info

- **Current Version:** 0.1.0
- **Engines:** Chromium, Firefox, Tor, Prism
- **Default Engine:** Firefox
- **Extensions:** Auto-installs uBlock Origin (when backend works)

---

Enjoy using Prism Browser! 🎉

