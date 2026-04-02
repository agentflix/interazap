# Windows Build Instructions

## Requirements

- Windows 10/11 x64
- Node.js 20+
- Electron (already in node_modules)

## Build Command

```bash
cd electron
npm run electron:build:win
```

## Output

- `release/InteraZap Desktop Setup 1.0.0.exe` (NSIS installer)

## Notes

- NSIS installer supports:
    - Custom install directory
    - Desktop shortcut
    - Start menu shortcut
- WebView2 runtime is required (usually pre-installed on Win10/11)
- Cross-compilation from macOS is not supported; build must run on Windows

## Icon Requirements

Place a valid Windows icon at:

```
electron/build/icon.ico
```

The icon must be:

- Format: ICO (multi-resolution)
- Recommended sizes: 16x16, 32x32, 48x48, 256x256
- Can be generated from a 256x256 PNG using tools like `png2icons` or online converters

## Build Configuration

The build uses the configuration in `electron-builder.yml`:

```yaml
win:
    target:
        - target: nsis
          arch:
              - x64
    icon: build/icon.ico
nsis:
    oneClick: false
    allowToChangeInstallationDirectory: true
    createDesktopShortcut: true
    createStartMenuShortcut: true
```
