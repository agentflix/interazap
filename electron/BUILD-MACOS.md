# macOS Build Instructions

## Requirements

- macOS 10.15+ (Catalina or later)
- Xcode command line tools
- Apple Developer ID (for production signing)

## Development Build (Ad-hoc signing)

```bash
cd electron
npm run electron:build:mac
```

## Production Build (With Apple Developer signing)

Set environment variables:

```bash
export ELECTRON_MAC_SIGNING_IDENTITY="Developer ID Application: Your Name (TEAMID)"
export ELECTRON_MAC_NOTARIZATION_APPLE_ID="your@email.com"
export ELECTRON_MAC_NOTARIZATION_APPLE_PASSWORD="app-specific-password"
npm run electron:build:mac
```

## Output

- `release/InteraZap Desktop-1.0.0.dmg`

## Notes

- DMG supports drag-to-Applications installation
- Hardened Runtime required for distribution outside App Store
- Notarization recommended but not required for internal distribution
