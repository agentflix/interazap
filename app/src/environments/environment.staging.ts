export const environment = {
  production: true,
  mobile: false,
  apiUrl: 'https://stage.api.interazap.com.br/api',
  gateway: {
    url: 'https://stage.gateway.interazap.com.br',
    path: '/ws',
    internalApiKey: '',
  },
  publicWebchatUrl: 'https://stage.app.interazap.com.br',
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
