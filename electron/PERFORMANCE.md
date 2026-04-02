# Performance Targets

## Startup

| Metric | Target |
|--------|--------|
| Cold start | < 5 seconds |
| Warm start | < 2 seconds |
| Memory (idle) | < 150MB |

## Bundle Size

| Platform | Target |
|----------|--------|
| Windows | ~180MB |
| macOS | ~170MB |
| Linux | ~165MB |

## Resource Usage

| Resource | Limit |
|----------|-------|
| RAM (normal) | < 300MB |
| RAM (video call) | < 500MB |
| CPU (idle) | < 2% |

## Optimization Strategies

### 1. Cold Start Optimization
- Lazy load Angular modules
- Use `loadChildren` for routes
- Defer non-critical initializations

### 2. Bundle Size
- Enable Angular build optimizer
- Tree-shaking in production
- Keep dependencies minimal

### 3. Memory Management
- Use OnPush change detection
- Unsubscribe from observables properly
- Clear timers/intervals on destroy

### 4. Electron Main Process
- Lazy load IPC handlers
- Minimize main process work at startup
- Use `app.whenReady()` correctly

## Verification

### Manual Testing
1. Measure cold start: `console.time()` in main.ts
2. Check memory: Chrome DevTools > Memory tab
3. Profile CPU: Chrome DevTools > Performance tab

### Automated Testing
```bash
# Start time measurement
npm run electron:dev

# In DevTools console:
console.time('windowLoad');
window.addEventListener('load', () => console.timeEnd('windowLoad'));
```

## Known Limitations

- Cold start includes Electron + Chromium startup (~2-3s overhead)
- Angular bundle size depends on app complexity
- Memory target may vary with active features
