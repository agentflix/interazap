export const environment = {
  production: true,
  apiUrl: 'https://api.agentflix.com.br/api',
  gateway: {
    url: 'https://gateway.agentflix.com.br',
    path: '/ws',
  },
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
