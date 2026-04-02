export const environment = {
  production: false,
  apiUrl: '/api',
  gateway: {
    url: '',
    path: '/ws',
  },
  sentry: {
    dsn: '', // Disabled in development
    tracesSampleRate: 0,
    profilesSampleRate: 0,
  },
};
