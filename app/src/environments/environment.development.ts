export const environment = {
  production: false,
  mobile: false,
  apiUrl: '/api',
  gateway: {
    url: '',
    path: '/ws',
  },
  publicWebchatUrl: 'http://localhost:4200',
  sentry: {
    dsn: '', // Disabled in development
    tracesSampleRate: 0,
    profilesSampleRate: 0,
  },
};
