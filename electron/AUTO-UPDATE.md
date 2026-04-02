# Auto-Update Configuration

## Overview

Electron uses [electron-updater](https://www.electron.build/auto-update) with GitHub Releases as the update server.

## Configuration

```yaml
# electron-builder.yml
publish:
  provider: github
  owner: agentflix
  repo: agentflix-desktop
```

## Update Flow

1. App starts - checks GitHub Releases for new version
2. If update available - notification shown to user
3. User clicks "Download" - update downloaded in background
4. User clicks "Install" - app restarts with new version

## Implementation

The updater service is implemented in `services/updater.ts`:

- `updater:check` - checks for available updates
- `updater:download` - downloads the update in background
- `updater:install` - quits and installs the new version

Auto-update is disabled in development (NODE_ENV !== 'production').

## GitHub Releases Setup

### Creating a Release

1. Build the app for the target platform:
   ```bash
   pnpm electron:build:win   # Windows
   pnpm electron:build:mac  # macOS
   pnpm electron:build:linux # Linux
   ```

2. Create and push a tag:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. Create the GitHub Release:
   - Go to: https://github.com/agentflix/agentflix-desktop/releases/new
   - Select the tag version (e.g., v1.0.0)
   - Title: Version 1.0.0
   - Attach the built artifacts from `release/` folder:
     - Windows: `.exe` installer
     - macOS: `.dmg` file
     - Linux: `.AppImage`, `.deb`, `.rpm`

### Update Server URL

Updates are served from: `https://github.com/agentflix/agentflix-desktop/releases`

## Local Development

Auto-update is disabled when `NODE_ENV !== 'production'`. To test updates locally:

1. Build the app in production mode
2. Create a GitHub release with the artifacts
3. Run the previous version - it should detect the new release

## Security

- Updates are verified via GitHub's signature
- Only authenticated releases can distribute updates
- The `electron-updater` validates the update before installation
