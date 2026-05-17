# Validation Flow — InteraZap

Gates de qualidade obrigatórios. Falhou? Volta para EXECUTION.

## Princípios

1. Gates são inegociáveis.
2. Toda task que altera um workspace deve rodar os gates daquele workspace.
3. Coverage backend deve ser >= 80% com Pest.
4. Coverage app deve ser >= 70% com Vitest.
5. Testes pulados (`skipped`) não passam.
6. Build limpo é obrigatório.

## Gate Matrix por Workspace

### `api/` — Laravel 12 / PHP 8.3

```bash
cd api
composer format
composer analyse
composer test
composer test -- --coverage
composer refactor

# Atalho
composer gate:all
```

**Critérios:**

- [ ] Pint: 0 violações.
- [ ] PHPStan: 0 erros no nível configurado.
- [ ] Pest: 0 falhas, 0 skipped, coverage >= 80%.
- [ ] Rector: 0 sugestões pendentes ou justificadas.
- [ ] Migrations rodam: `php artisan migrate --pretend`.

### `gateway/` — NestJS 11 / TypeScript

```bash
pnpm --filter gateway lint
pnpm --filter gateway test
pnpm --filter gateway test:e2e
pnpm --filter gateway build
```

**Critérios:**

- [ ] ESLint: 0 warnings, 0 errors.
- [ ] Jest/Vitest: 0 falhas.
- [ ] E2E, se aplicável: 0 falhas.
- [ ] Build: `dist` gerado sem erros.

### `app/` — Angular 19 + Ionic + Capacitor

```bash
pnpm --filter app lint
pnpm --filter app test
pnpm --filter app build
./scripts/validate-app-gateway-boundary.sh
```

**Critérios:**

- [ ] ESLint: 0 warnings, 0 errors.
- [ ] Vitest: 0 falhas, coverage >= 70%.
- [ ] Build: sucesso, sem warnings críticos.
- [ ] Bundle dentro do budget configurado em `angular.json`.
- [ ] Browser não usa `environment.gateway.url` fora do `RealtimeService`.

### `electron/` — Electron + Angular

```bash
pnpm --filter electron build

# Apenas em release
pnpm --filter electron dist
```

**Critérios:**

- [ ] Build TypeScript: sucesso.
- [ ] Renderer Angular: build sem erros.
- [ ] `electron-builder`: empacotamento ok, apenas em release.

### `landing/`

```bash
pnpm --filter landing build
```

**Critérios:**

- [ ] Build limpo.

## Critérios Gerais

- [ ] Testes escritos cobrindo o comportamento da seção C do T.A.C.E.
- [ ] Critérios de aceite da seção E do T.A.C.E atendidos.
- [ ] Sem segredos no código.
- [ ] `.env` não commitado.
- [ ] PHPDoc/JSDoc onde aplicável.
- [ ] Sem `console.log` ou `dd()` esquecidos.
- [ ] Sem `it.skip` ou `test.skip` sem justificativa explícita.

## Critérios por Tipo de Mudança

### Multi-tenancy

- [ ] Trait `BelongsToTenant` aplicado.
- [ ] Teste de isolamento entre tenants.
- [ ] Policy + `authorize()` no controller.

### Webhook

- [ ] Idempotência via Redis com chave + TTL.
- [ ] Validação HMAC.
- [ ] Teste de webhook duplicado.
- [ ] Logs estruturados.

### Integração Externa

- [ ] Circuit breaker.
- [ ] Retry exponencial.
- [ ] Timeout explícito.
- [ ] Logs estruturados em request/response, sem segredos.

### Migration

- [ ] `up()` e `down()` implementados.
- [ ] FK com `on delete` explícito.
- [ ] Índices em `tenant_id` e FKs frequentemente filtradas.
- [ ] Testado em banco local antes de mergear.

### WebSocket / Real-time

- [ ] Autenticação no handshake.
- [ ] Eventos tipados em Gateway e Frontend.
- [ ] Teste de broadcast/unicast.

## Quando QA Reprova

1. Anotar motivo no arquivo de tasks com status `Reprovada` e comentário.
2. Voltar para EXECUTION.
3. Após correção, executar nova rodada de VALIDATION.
4. Se for uma armadilha não óbvia, criar entrada em MEMORY do tipo `Armadilha`.
