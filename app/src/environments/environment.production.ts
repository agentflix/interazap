export const environment = {
  production: true,
  mobile: false,
  apiUrl: 'https://api.interazap.com.br/api',
  gateway: {
    url: 'https://gateway.interazap.com.br',
    path: '/ws',
    internalApiKey: '',
  },
  publicWebchatUrl: 'https://app.interazap.com.br',
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
