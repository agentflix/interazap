export const environment = {
  production: true,
  apiUrl: '/api',
  gateway: {
    url: '',
    path: '/ws',
  },
  sentry: {
    dsn: '', // Set via environment variable at build time
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
