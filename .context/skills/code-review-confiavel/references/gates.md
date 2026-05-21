# Gates do Review

Rode apenas os gates dos workspaces alterados. Se um comando não puder rodar, registre motivo e risco residual.

## API — `api/`

```bash
cd api && composer gate:all
```

`composer gate:all` na API executa `composer test`, que deve usar Pest com `--parallel --exclude-testsuite=E2E`. E2E roda separado via `composer test:e2e` quando necessário.
Para ciclo rápido de desenvolvimento/IA, é permitido `cd api && composer gate:fast`, mas não substitui o gate completo.
Se precisar incluir commits já feitos na branch no incremental: `cd api && PHPSTAN_BASE_REF=origin/main composer analyse:changed`.

Quando precisar isolar falha:

```bash
cd api && composer format
cd api && composer analyse
cd api && composer test
cd api && composer refactor
```

## Gateway — `gateway/`

```bash
pnpm --filter gateway test && pnpm --filter gateway build
```

Quando aplicável:

```bash
pnpm --filter gateway lint
pnpm --filter gateway test:e2e
pnpm --filter gateway typecheck:specs
```

## App — `app/`

```bash
pnpm --filter app test && pnpm --filter app build
```

Quando aplicável:

```bash
pnpm --filter app lint
./scripts/validate-app-gateway-boundary.sh
```

## Electron — `electron/`

```bash
pnpm --filter electron build
```

## Landing — `landing/`

```bash
pnpm --filter landing build
```

## Critérios de Falha

- Teste falhou.
- Build falhou.
- Lint/typecheck falhou.
- Coverage mínimo aplicável não foi atendido.
- Teste pulado sem justificativa explícita.
- Gate obrigatório não foi executado sem justificativa.
