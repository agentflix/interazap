# InteraZap Desktop

Multi-OS Desktop Application built with Electron + Angular.

## Quick Start

### Prerequisites

- Node.js 20+
- npm 10+

### Installation

```bash
cd electron
npm install
```

### Development

```bash
# Terminal 1: Start Angular dev server
cd ../app
npm run start

# Terminal 2: Start Electron
cd electron
npm run electron:dev
```

### Build

```bash
# Build for current platform
npm run electron:build

# Build for specific platforms
npm run electron:build:win    # Windows
npm run electron:build:mac  # macOS
npm run electron:build:linux # Linux
```

## Project Structure

```
electron/
├── main.ts           # Main process entry
├── preload.ts        # Context bridge
├── ipc/              # IPC handlers
│   ├── window.ts
│   ├── system.ts
│   ├── notifications.ts
│   ├── file-system.ts
│   └── screen-capture.ts
├── services/         # Services
│   └── updater.ts
├── tray.ts           # System tray
├── shortcuts.ts      # Global shortcuts
└── dist/            # Compiled output
```

## Features

- Custom title bar with window controls
- System tray with menu
- Global shortcuts
- Screen capture
- Native notifications
- File system access
- Auto-update
- Offline mode support

## Documentation

- [Windows Build](BUILD-WINDOWS.md)
- [macOS Build](BUILD-MACOS.md)
- [Linux Build](BUILD-LINUX.md)
- [Auto-Update](AUTO-UPDATE.md)
- [Performance](PERFORMANCE.md)

## License

MIT
