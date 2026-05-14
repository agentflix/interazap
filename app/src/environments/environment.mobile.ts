export const environment = {
  production: true,
  mobile: true,
  apiUrl: 'https://api.interazap.com.br/api',
  gateway: {
    url: 'https://gateway.interazap.com.br',
    path: '/ws',
    internalApiKey: '',
  },
  publicWebchatUrl: 'https://app.interazap.com.br',
  sentryEnvironment: 'mobile-production',
  sentry: {
    // Substitua pelo DSN real do projeto Sentry (Settings → Client Keys → DSN)
    dsn: '',
    tracesSampleRate: 0.1,
    profilesSampleRate: 0.1,
  },
};
