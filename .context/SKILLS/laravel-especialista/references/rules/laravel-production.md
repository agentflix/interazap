# Regras Laravel: Produção

Use estas regras ao mexer em configuração, deploy, scheduler, cache ou endpoints operacionais.

## Configuração

- Produção deve usar `APP_DEBUG=false`.
- Nunca commitar segredos; usar env/secret manager.
- Rodar em release: `config:cache`, `route:cache`, `event:cache` e `view:cache` quando aplicável.
- Limpar caches e reiniciar Octane/Horizon quando config ou código mudar.

## Deploy

- Deploy deve ser sem downtime quando possível: preparar release, rodar migrations seguras, aquecer caches e trocar symlink/container.
- Migrations destrutivas devem ser em fases quando houver tráfego: expandir, migrar dados, contrair.
- Workers antigos devem encerrar graciosamente; não matar job crítico no meio sem retry seguro.

## Scheduler

- Tarefas globais devem usar lock para evitar execução duplicada em multi-instância.
- Tarefas por tenant devem registrar progresso e tolerar retomada.
- Jobs disparados pelo scheduler devem ser idempotentes.

## Horizon e Queues

- Horizon deve rodar supervisionado pelo sistema/containers.
- Configurar notificações de falha e long wait.
- Separar supervisores por fila quando a latência importar.

## Endpoints Operacionais

- `/health` deve ter dependência mínima e não expor segredo.
- `/metrics` deve ser protegido por throttle e/ou rede interna.
- Endpoints administrativos devem ter policy, auditoria e rate limit quando necessário.
