export const environment = {
  production: true,
  apiUrl: 'https://stage.api.agentflix.com.br/api',
  gateway: {
    url: 'https://stage.gateway.agentflix.com.br',
    path: '/ws',
  },
  sentry: {
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
