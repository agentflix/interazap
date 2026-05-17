# Regras Laravel: PHP Runtime e OPcache

Use estas regras ao ajustar ambiente PHP 8.3, workers CLI, Octane, deploy ou performance de runtime.

## OPcache

- OPcache deve estar habilitado em produção para HTTP e CLI quando workers long-lived precisarem de bootstrap rápido.
- Em deploy, limpar/recarregar OPcache ao publicar nova versão.
- Usar `opcache.validate_timestamps=0` em produção imutável com reload no deploy; em ambientes mutáveis, manter validação com intervalo controlado.
- Aumentar `opcache.memory_consumption` e `opcache.max_accelerated_files` quando o monorepo/app crescer.
- Habilitar `opcache.preload` apenas se houver script mantido e testado; preload mal cuidado quebra deploy.
- Não assumir que JIT melhora Laravel; testar antes. Para apps IO-bound, JIT tende a ajudar pouco.

## Composer E Bootstrap

- Em produção, usar autoload otimizado: `composer install --no-dev --optimize-autoloader`.
- Rodar caches Laravel no release, não em runtime de request.
- Evitar descobrir pacotes ou gerar arquivos durante request.

## Limites

- `memory_limit` deve comportar picos reais, mas jobs pesados precisam de fila dedicada e `memory` do Horizon.
- `max_execution_time` HTTP deve ser curto; tarefas longas vão para queue.
- Workers CLI devem ter `timeout` menor que `retry_after` da fila.
- Ajustar `realpath_cache_size` para reduzir custo de filesystem em deploys grandes.

## Long-lived Processes

- Workers de queue e Octane devem reiniciar por `maxJobs`, `maxTime` ou deploy.
- Monitorar crescimento de memória por worker.
- Não guardar tenant, usuário, request, token ou config mutável em propriedades estáticas/singletons persistentes.
