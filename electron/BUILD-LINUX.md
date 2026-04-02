# Linux Build Instructions

## Requirements

- Linux x64 (Ubuntu 20.04+, Fedora 36+, or equivalent)
- Node.js 20+
- electron-builder dependencies

## Build Command

```bash
cd electron
npm run electron:build:linux
```

## Output

- `release/InteraZap Desktop-1.0.0.AppImage` (Portable)
- `release/interazap-desktop_1.0.0_amd64.deb` (Debian/Ubuntu)
- `release/interazap-desktop-1.0.0.x86_64.rpm` (Fedora/RHEL)

## AppImage Notes

- Portable - no installation required
- Works on most modern Linux distributions

## DEB/RPM Notes

- System installation with dependency management
- Desktop entry file included
- AppIndicator support for system tray
