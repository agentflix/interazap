import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.interazap.app',
  appName: 'InteraZap',
  webDir: 'dist/app-new/browser',
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor: '#14b8a6',
      showSpinner: false,
      androidSpinnerStyle: 'large',
      iosSpinnerStyle: 'small',
      splashFullScreen: true,
      splashImmersive: true,
    },
    Keyboard: {
      resize: 'native',
    },
  },
};

export default config;
