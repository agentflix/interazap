export const environment = {
  production: true,
  apiUrl: 'https://api.interazap.com.br/api',
  gateway: {
    url: 'https://gateway.interazap.com.br',
    path: '/ws',
  },
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
