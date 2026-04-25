export const environment = {
  production: true,
  apiUrl: 'https://api.interazap.com.br/api',
  gateway: {
    url: 'https://gateway.interazap.com.br',
    path: '/ws',
  },
  publicWebchatUrl: 'https://app.interazap.com',
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
