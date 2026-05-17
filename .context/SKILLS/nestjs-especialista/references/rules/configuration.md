# Configuração

## Configuração tipada

- Adicione variáveis em `core/config/configuration.ts` e seus tipos em `models/configuration.model.ts`.
- Use parsing seguro para números, booleanos e listas.
- Defina fallback apenas quando for seguro para desenvolvimento; em produção, segredo ausente deve falhar fechado.
- Não leia `process.env` espalhado pelo domínio quando `ConfigService` já resolve o caso.

## `.env`

- Atualize `.env.example` quando adicionar variável necessária.
- Nunca coloque segredo real em exemplo, teste ou documentação.
- Nomeie variáveis por domínio: `OPENAI_*`, `ASAAS_*`, `UAZAPI_*`, `INTERNAL_*`, `WS_*`, `THROTTLE_*`.

## Deploy

- Build deve funcionar com `nest build`.
- Configuração de CORS, body limit, throttling, Redis e providers deve vir de ambiente.
- Mudança de configuração relevante deve ser registrada em MEMORY quando afetar operação, segurança ou disponibilidade.
