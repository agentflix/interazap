# Configuração E Ambiente

## ConfigModule

- Adicione novas variáveis em `gateway/src/core/config/configuration.ts` e tipagem correspondente em `models/configuration.model.ts`.
- Use `registerAs` por domínio de configuração.
- Defina defaults seguros apenas quando não criarem falsa sensação de produção pronta.
- Parseie números e booleanos com fallback explícito.

## Variáveis

- Variáveis sensíveis não devem ter valor real no repositório.
- Para segredos, prefira `SecretsService` ou secret manager quando o padrão local já existir.
- Preserve nomes existentes como `REDIS_URL`, `DATABASE_URL`, `API_URL`, `INTERNAL_API_KEY`, `JWT_SECRET`, `WEBCHAT_JWT_SECRET`, `OPENAI_API_KEY`, `ASAAS_API_KEY` e configurações de providers.

## CORS E Segurança HTTP

- CORS deve ser restritivo por ambiente e compatível com App/Electron.
- Preserve Helmet no bootstrap.
- Ao adicionar headers aceitos, valide necessidade real e impacto em preflight.

## Testes

- Teste parsing de configuração, fallback e ausência de variável obrigatória.
- Não dependa do `.env` real em unit tests; injete valores pelo ambiente do teste ou mock de `ConfigService`.
