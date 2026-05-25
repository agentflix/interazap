# TESTE_ADMINISTRACAO.md - Plano de Testes Playwright de Administracao

## Resumo

Este arquivo e o mapa operacional para testar as telas de Administracao/Monitoramento com `playwright-cli`.

Nao iniciar testes sem confirmacao explicita. Este modulo possui acoes operacionais destrutivas ou sensiveis. Nunca executar `Limpar`, `Retentar todos`, `Limpar tudo`, `Pausar fila`, `Forcar abertura` ou `Resetar circuit breaker` sem confirmar que o ambiente e descartavel.

Ambiente assumido:

- Frontend Angular: `http://localhost:4200`
- API local via proxy: `/api`
- Login seed: `admin@interazap.com.br` / `password`
- Permissoes/admin: `users.role.manage` e admin guard liberado
- Redis/gateway disponivel para metricas de filas

Escopo:

- Dashboard de Filas (`/admin/queues`)
- Dead Letter Queue (`/admin/queues/dlq`)
- Circuit Breakers (`/admin/queues/circuits`)

## Protocolo De Execucao

1. Abrir login:

   ```bash
   playwright-cli open http://localhost:4200/auth/login
   ```

2. Fazer login e salvar estado:

   ```bash
   playwright-cli state-save .playwright-admin-auth.json
   ```

3. Antes de cada tela:

   ```bash
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

4. Em falha:

   ```bash
   playwright-cli screenshot --filename=admin-failure-<ID>.png
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

## Criterios Globais

### Sucesso

- Rotas abrem apenas para admin.
- Loading termina.
- Indicadores numericos renderizam mesmo com zero.
- Realtime/conexao mostra `Conectado` ou `Desconectado` sem quebrar tela.
- Tabelas mostram dados ou estado vazio controlado.
- Acoes sensiveis possuem loading/desabilitacao adequada.

### Falha

- Usuario admin cai em `/access-denied`.
- Tela branca ou console error inesperado.
- Request `/api/admin/queues/**` retorna 500.
- Botao sensivel executa duas vezes por duplo clique.
- Estado vazio nao aparece quando lista e vazia.
- Modal de detalhes/stacktrace nao abre ou nao fecha.

## ADMIN-00 - Login E Menu Monitoramento

### Passos

1. Fazer login como admin.
2. Abrir grupo `Monitoramento` no sidenav.
3. Validar links:
   - Dashboard de Filas
   - Dead Letter Queue
   - Circuit Breakers
4. Abrir cada link.

### Passou Se

- Menu aparece para admin.
- Rotas carregam sem redirect indevido.

## ADMIN-01 - Dashboard De Filas

**Rota:** `/admin/queues`
**Endpoints esperados:** `/api/admin/queues`, fallback possivel em `/api/admin/health/queues`

### Passos

1. Abrir `/admin/queues`.
2. Validar titulo `Dashboard de Filas`.
3. Validar badge `Conectado` ou `Desconectado`.
4. Validar botao `Atualizar`.
5. Validar cards:
   - Total de Jobs
   - Concluidos
   - Falhos
   - Redis
6. Validar tabela `Filas`.
7. Validar colunas:
   - Nome
   - Aguardando
   - Ativos
   - Concluidos
   - Falhos
   - Atrasados
   - Status
   - Acoes
8. Clicar `Atualizar`.
9. Aguardar loading encerrar.
10. Abrir link `Dead Letter Queue`.
11. Voltar.
12. Abrir link `Circuit Breakers`.

### Passou Se

- Numeros aparecem como zero ou valor real.
- Redis mostra conectado/desconectado.
- Tabela mostra filas ou estado `Nenhuma fila encontrada`.
- Atualizar refaz request.

### Acoes Sensiveis Opcionalmente Executaveis

Executar apenas em ambiente descartavel:

1. Escolher fila de teste.
2. Clicar `Pausar fila`.
3. Validar status `Pausada`.
4. Clicar `Resumir fila`.
5. Validar status `Ativa`.
6. Se houver falhas, clicar `Limpar jobs falhos`.
7. Validar reducao de falhos ou feedback de sucesso.

### Falha E Correcao

- Se acoes nao atualizam, verificar `queue.service.ts` metodos `pauseQueue`, `resumeQueue`, `cleanQueue`.
- Se realtime desconecta sempre, verificar `RealtimeService` e gateway.

## ADMIN-02 - Dead Letter Queue: Lista E Filtros

**Rota:** `/admin/queues/dlq`
**Endpoint esperado:** `/api/admin/queues/dlq`

### Passos

1. Abrir `/admin/queues/dlq`.
2. Validar titulo `Dead Letter Queue`.
3. Validar botoes:
   - Voltar
   - Limpar tudo
   - Retentar todos
   - Atualizar
4. Validar filtros:
   - Fila
   - Tipo de Job
   - Data Inicial
   - Data Final
5. Preencher Tipo de Job com `E2EJob`.
6. Preencher Data Inicial e Data Final.
7. Validar indicador `Filtros ativos`.
8. Clicar `Limpar filtros`.
9. Validar cards:
   - Total na DLQ
   - Ultima Falha
   - Filas Afetadas
10. Validar tabela ou estado vazio.
11. Se houver job, validar colunas:
   - Job
   - Fila
   - Erro
   - Tentativas
   - Falhou em
   - Acoes

### Passou Se

- Filtros disparam request com query params.
- Estado vazio `Nenhum job na Dead Letter Queue` aparece quando nao ha jobs.
- Lista mostra job id, nome, fila, erro truncado e tentativas quando ha jobs.

## ADMIN-03 - Dead Letter Queue: Detalhes E Stacktrace

**Pre-condicao:** existir ao menos 1 job na DLQ.

### Passos

1. Abrir `/admin/queues/dlq`.
2. Em um job, clicar `Ver detalhes`.
3. Validar modal/detalhe com id, nome, fila, erro e payload/metadados quando disponivel.
4. Fechar detalhe.
5. Se o job tiver stacktrace, clicar `Ver stacktrace`.
6. Validar texto do stacktrace.
7. Fechar modal.

### Passou Se

- Detalhes abrem sem quebrar layout.
- Stacktrace e legivel e rolavel.
- Fechar retorna para tabela.

## ADMIN-04 - Dead Letter Queue: Retentar E Remover Job

**Pre-condicao:** ambiente descartavel e ao menos 1 job de teste na DLQ.

### Passos

1. Abrir `/admin/queues/dlq`.
2. Selecionar um job de teste.
3. Clicar `Retentar job`.
4. Validar loading no botao.
5. Confirmar feedback ou remocao/reducao do item.
6. Se ainda houver job de teste, clicar `Remover job`.
7. Confirmar acao se houver modal.
8. Validar item removido.

### Passou Se

- Retentar chama `/api/admin/queues/dlq/:jobId/retry`.
- Remover chama DELETE `/api/admin/queues/dlq/:jobId`.
- Tabela atualiza depois da acao.

### Falha E Correcao

- Se loading fica preso, verificar `dead-letter-queue.ts`.
- Se item nao atualiza, verificar refresh apos acao em `QueueService`.

## ADMIN-05 - Dead Letter Queue: Retentar Todos E Limpar Tudo

**Pre-condicao:** ambiente descartavel e autorizacao explicita.

### Passos

1. Abrir `/admin/queues/dlq`.
2. Aplicar filtro que selecione apenas jobs de teste.
3. Clicar `Retentar todos`.
4. Confirmar se houver modal.
5. Validar reducao da lista.
6. Recriar/garantir jobs de teste, se necessario.
7. Aplicar o mesmo filtro.
8. Clicar `Limpar tudo`.
9. Confirmar se houver modal.
10. Validar estado vazio ou reducao.

### Passou Se

- Acoes respeitam filtros atuais.
- Botoes ficam desabilitados durante loading.
- Nenhum job fora do filtro e afetado.

## ADMIN-06 - Circuit Breakers: Visao Geral

**Rota:** `/admin/queues/circuits`
**Endpoint esperado:** `/api/admin/queues/circuits`

### Passos

1. Abrir `/admin/queues/circuits`.
2. Validar titulo `Circuit Breakers`.
3. Validar botao `Voltar`.
4. Validar botao `Atualizar`.
5. Validar cards:
   - Fechados
   - Semi-Abertos
   - Abertos
6. Validar lista/cards de circuit breakers.
7. Para cada circuito visivel, validar:
   - Nome
   - Estado: CLOSED, HALF_OPEN ou OPEN
   - Contadores/metricas
   - Ultima alteracao ou informacao temporal quando disponivel
8. Clicar `Atualizar`.

### Passou Se

- Cards renderizam zero ou valor real.
- Circuitos aparecem ou estado vazio controlado.
- Estados usam cor/badge coerente.

## ADMIN-07 - Circuit Breakers: Acoes Controladas

**Pre-condicao:** ambiente descartavel e autorizacao explicita.

### Passos

1. Abrir `/admin/queues/circuits`.
2. Escolher um circuit breaker de teste.
3. Se existir acao `Resetar`, clicar.
4. Validar estado volta para fechado/CLOSED.
5. Se existir acao `Forcar abertura`, clicar apenas em circuito de teste.
6. Validar estado aberto/OPEN.
7. Atualizar tela.
8. Resetar novamente para nao deixar ambiente degradado.

### Passou Se

- Acoes chamam endpoints corretos.
- Loading impede duplo clique.
- Estado final e restaurado para seguro.

### Falha E Correcao

- Se estado nao muda, verificar endpoints `resetCircuitBreaker` e `forceOpenCircuitBreaker`.
- Se pagina nao reflete apos request 2xx, verificar refresh da tela.

## ADMIN-08 - Permissoes E Guards

### Passos

1. Entrar com usuario sem permissao/admin, se existir.
2. Tentar abrir `/admin/queues`.
3. Validar redirect para acesso negado ou bloqueio.
4. Tentar abrir `/admin/queues/dlq`.
5. Tentar abrir `/admin/queues/circuits`.
6. Entrar novamente como admin.
7. Validar acesso liberado nas tres rotas.

### Passou Se

- AdminGuard e PermissionGuard bloqueiam usuarios sem permissao.
- Usuario admin acessa todo o modulo.

## Checklist Final Do Modulo

- [ ] Menu Monitoramento validado.
- [ ] Dashboard de filas abre.
- [ ] Cards de jobs/Redis validados.
- [ ] Tabela de filas validada.
- [ ] Atualizar validado.
- [ ] Links para DLQ e circuitos validados.
- [ ] DLQ lista/filtros validados.
- [ ] DLQ detalhe/stacktrace validado quando houver job.
- [ ] Acoes DLQ executadas apenas com autorizacao.
- [ ] Circuit breakers visao geral validada.
- [ ] Acoes de circuit breaker executadas apenas com autorizacao.
- [ ] Guards/permissoes validados.
