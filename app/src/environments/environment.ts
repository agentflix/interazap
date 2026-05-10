export const environment = {
  production: false,
  mobile: false,
  apiUrl: '/api',
  gateway: {
    url: '',
    path: '/ws',
    internalApiKey: '',
  },
  publicWebchatUrl: 'http://localhost:4200',
  sentry: {
    dsn: '', // Set via environment variable at build time
    tracesSampleRate: 0,
    profilesSampleRate: 0,
  },
};
