# Validation Flow — InteraZap

> Gates de qualidade obrigatórios. Falhou? Volta para EXECUTION.

## Princípios

1. Gates são **inegociáveis**
2. Toda task tocando um workspace DEVE rodar os gates daquele workspace
3. Coverage backend ≥ 80% (Pest)
4. Coverage app ≥ 70% (Vitest)
5. Tests pulados (`skipped`) **não passam**
6. Build limpo é obrigatório

---

## Gate Matrix por Workspace

### api/ (Laravel 12 / PHP 8.3)

```bash
cd api

# Format (Pint)
composer format

# Static analysis (PHPStan L6 + Larastan)
composer analyse

# Tests (Pest)
composer test
composer test -- --coverage

# Refactor check (Rector dry-run)
composer refactor

# Atalho — roda tudo:
composer gate:all
```

**Critérios:**
- [ ] Pint: 0 violações
- [ ] PHPStan: 0 erros (L6)
- [ ] Pest: 0 falhas, 0 skipped, ≥ 80% coverage
- [ ] Rector: 0 sugestões pendentes ou justificadas
- [ ] Migrations rodam (`php artisan migrate --pretend`)

### gateway/ (NestJS 11 / TypeScript 5.7)

```bash
pnpm --filter gateway lint
pnpm --filter gateway test
pnpm --filter gateway test:e2e
pnpm --filter gateway build
```

**Critérios:**
- [ ] ESLint: 0 warnings, 0 errors
- [ ] Jest/Vitest: 0 falhas
- [ ] E2E (se aplicável): 0 falhas
- [ ] Build: dist gerado sem erros

### app/ (Angular 19 + Ionic + Capacitor)

```bash
pnpm --filter app lint
pnpm --filter app test
pnpm --filter app build
```

**Critérios:**
- [ ] ESLint: 0 warnings, 0 errors
- [ ] Vitest: 0 falhas, ≥ 70% coverage
- [ ] Build (`ng build`): sucesso, sem warnings críticos
- [ ] Tamanho do bundle dentro do budget configurado (`angular.json`)

### electron/ (Electron 33 + Angular 20)

```bash
pnpm --filter electron build
# Apenas em release:
# pnpm --filter electron dist
```

**Critérios:**
- [ ] Build TS: sucesso
- [ ] Renderer (Angular 20): build sem erros
- [ ] electron-builder: empacotamento ok (apenas em release)

### landing/

```bash
pnpm --filter landing build
```

**Critérios:**
- [ ] Build limpo

---

## Critérios Gerais (toda task)

- [ ] Testes escritos cobrindo o comportamento da seção C (T.A.C.E)
- [ ] Critérios de aceite (seção E do T.A.C.E) atendidos
- [ ] Sem segredos no código (`.env` não commitado)
- [ ] phpDoc / JSDoc onde aplicável
- [ ] Sem `console.log` ou `dd()` esquecidos
- [ ] Sem `it.skip` / `test.skip` sem justificativa explícita

---

## Critérios Específicos por Tipo de Mudança

### Mudança em Multi-tenancy
- [ ] Trait `BelongsToTenant` aplicado
- [ ] Teste de isolamento entre tenants (acesso negado de outro tenant)
- [ ] Policy + `authorize()` no Controller

### Webhook (UazAPI / Z-API / Asaas)
- [ ] Idempotência via Redis (chave + TTL)
- [ ] Validação HMAC
- [ ] Teste de webhook duplicado (não causa efeito colateral)
- [ ] Logs estruturados

### Integração Externa (OpenAI / Asaas / providers)
- [ ] Circuit breaker
- [ ] Retry exponencial
- [ ] Timeout explícito
- [ ] Logs estruturados em request/response

### Migration
- [ ] `up()` e `down()` implementados
- [ ] FK com `on delete` explícito
- [ ] Índices em `tenant_id` e FKs frequentemente filtradas
- [ ] Testado em DB local antes de mergear

### WebSocket / Real-time
- [ ] Autenticação no handshake
- [ ] Eventos tipados (TS) em ambos os lados (Gateway + Frontend)
- [ ] Teste de broadcast / unicast

---

## Quando QA reprova

1. Anotar motivo no arquivo de tasks (status `Reprovada` + comentário)
2. Volta para EXECUTION
3. Após correção → nova rodada de Validation
4. Se for armadilha não óbvia → entrada em MEMORY (tipo `Armadilha`)
