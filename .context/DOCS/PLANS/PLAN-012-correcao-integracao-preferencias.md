# PLAN-012 — Correção de Integração das Preferências de Usuário e Tenant

## Objetivo

Corrigir os gaps entre persistência e comportamento real das telas de preferências, garantindo que as configurações alterem efetivamente o runtime do sistema (frontend e backend), com cobertura de testes e validação de gates.

## Módulo relacionado

- Auth
- Configuration
- Platform
- Chat
- Frontend (Angular)

## PRD relacionado (se existir): PRD-CONFIG-001

## Escopo

### Incluído

- Integrar a grade de notificações da tela /settings/preferences com os endpoints reais de preferências de notificação (/notifications/preferences).
- Garantir que desabilitar canal/tipo realmente afete o despacho no backend (NotificationDispatcherService).
- Implementar aplicação real de preferências de aparência/acessibilidade no frontend:
    - fontSize (small/medium/large)
    - density (compact/normal/expanded)
    - highContrast
    - reducedMotion
- Corrigir semântica de sessionTimeout para suportar null (Nunca) de ponta a ponta.
- Corrigir e fortalecer testes backend/frontend dessa feature (incluindo caminhos e assertions).
- Validar tenant settings com cobertura mínima de persistência + autorização e definir estratégia de consumo global progressivo.

### Excluído

- Redesign visual completo da página de preferências.
- Implementação de preferências de IA/autopilot.
- Refatoração ampla de módulos não relacionados.

## Etapas propostas

1. Correção de contrato de dados e integração de notificações (Frontend + Backend Configuration).
2. Implementação de runtime tokens para fontSize/density/highContrast/reducedMotion (Frontend).
3. Correção de semântica sessionTimeout null no backend de preferências (Auth).
4. Aplicação de preferências de comportamento em pontos críticos do chat (quando aplicável, incremental e testável).
5. Correção e expansão de testes automatizados (Pest/Vitest) e execução dos gates.
6. Validação final (QA + REVIEWER) e documentação de evidências.

## Tasks derivadas

| Task                       | Descrição                                                                | Agente      | Status |
| -------------------------- | ------------------------------------------------------------------------ | ----------- | ------ |
| TASK-012-BACK-CONFIG       | Integrar persistência de notificações reais e filtros de despacho        | BACKEND     | todo   |
| TASK-012-FRONT-PREFERENCES | Aplicar preferências visuais e de acessibilidade no runtime              | FRONTEND    | todo   |
| TASK-012-BACK-AUTH         | Corrigir sessionTimeout null e deep-merge de preferências                | BACKEND     | todo   |
| TASK-012-FRONT-CHAT        | Conectar preferências de comportamento aos fluxos de chat prioritários   | FRONTEND    | todo   |
| TASK-012-TESTS             | Corrigir e ampliar cobertura de testes de preferências e tenant settings | QA          | todo   |
| TASK-012-VALIDATION        | Rodar gates, registrar evidências e concluir com revisão                 | QA/REVIEWER | todo   |

## Riscos e dependências

### Riscos

| Risco                                                           | Probabilidade | Impacto | Mitigação                                                                    |
| --------------------------------------------------------------- | ------------- | ------- | ---------------------------------------------------------------------------- |
| Aplicação de tokens visuais causar regressão de layout          | Média         | Alto    | Introduzir via variáveis CSS com fallback e smoke test em páginas críticas   |
| Mudança de contrato de notificações quebrar payload existente   | Média         | Alto    | Compatibilizar shape de payload e cobrir com testes de integração            |
| Correção de sessionTimeout impactar consumidores legados        | Baixa         | Médio   | Ajuste backward-compatible no DTO + testes unit/feature                      |
| Falta de consumo global de tenant settings manter gap funcional | Alta          | Médio   | Definir rollout incremental e começar por serviços com maior impacto visível |

### Dependências

- Endpoints de notificações já existentes em Configuration devem permanecer estáveis.
- ThemeService e camada de layout global precisam aceitar tokens adicionais.
- Permissões de acesso para tenant settings devem manter política atual.

## Estimativa

| Item                          | Valor              |
| ----------------------------- | ------------------ |
| Complexidade                  | Alta               |
| Camadas afetadas              | Backend / Frontend |
| Migrações necessárias         | Não                |
| Impacto em módulos existentes | Sim                |
