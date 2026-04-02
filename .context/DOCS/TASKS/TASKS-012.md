# TASKS-012 — Correções de Integração das Preferências

## Plano Relacionado

- PLAN-012 — Correção de Integração das Preferências de Usuário e Tenant

## Tasks

| ID                         | Descrição                                                              | Agente      | Status | Dependências                                                         |
| -------------------------- | ---------------------------------------------------------------------- | ----------- | ------ | -------------------------------------------------------------------- |
| TASK-012-BACK-CONFIG       | Integrar grade de notificações com ConfigurationNotificationPreference | BACKEND     | todo   | -                                                                    |
| TASK-012-FRONT-PREFERENCES | Aplicar fontSize/density/highContrast/reducedMotion no runtime         | FRONTEND    | todo   | TASK-012-BACK-CONFIG                                                 |
| TASK-012-BACK-AUTH         | Corrigir sessionTimeout null e contrato de resposta                    | BACKEND     | todo   | -                                                                    |
| TASK-012-FRONT-CHAT        | Conectar preferências comportamentais aos fluxos de chat               | FRONTEND    | todo   | TASK-012-BACK-AUTH                                                   |
| TASK-012-TESTS             | Corrigir testes quebrados e ampliar cobertura E2E/integração           | QA          | todo   | TASK-012-BACK-CONFIG, TASK-012-FRONT-PREFERENCES, TASK-012-BACK-AUTH |
| TASK-012-VALIDATION        | Executar gates, consolidar evidências e checklist final                | QA/REVIEWER | todo   | TASK-012-TESTS                                                       |

---

## TASK-012-BACK-CONFIG: Integrar grade de notificações com ConfigurationNotificationPreference

### Goal

Garantir que os controles de notificações da tela de preferências alterem as preferências reais consumidas pelo NotificationDispatcherService.

### Etapas

- [ ] Definir mapeamento canônico tipo x canal da UI para payload de /notifications/preferences.
- [ ] Expor no frontend service/store chamada de leitura e escrita em lote para /notifications/preferences.
- [ ] Ajustar serialização de quiet_start/quiet_end quando necessário.
- [ ] Validar persistência por tipo e canal via testes feature.
- [ ] Validar que NotificationDispatcherService respeita enabled/channels/quiet hours em teste de integração.

### Critérios de conclusão

- [ ] Desabilitar um tipo/canal na UI impede criação/dispatch naquele canal.
- [ ] Reabilitar volta a permitir dispatch.
- [ ] Horário de silêncio impede envio imediato e mantém status pendente quando aplicável.

---

## TASK-012-FRONT-PREFERENCES: Aplicar fontSize/density/highContrast/reducedMotion no runtime

### Goal

Transformar preferências visuais e de acessibilidade em comportamento efetivo no app, e não apenas persistência.

### Etapas

- [ ] Criar strategy de design tokens CSS globais para font-size e density (root classes/vars).
- [ ] Aplicar classe/atributo global para highContrast e reducedMotion.
- [ ] Garantir persistência e re-hidratação no bootstrap/login.
- [ ] Validar impacto em páginas críticas (dashboard, chat, crm listing).
- [ ] Adicionar testes unit/component para aplicação de classes/tokens.

### Critérios de conclusão

- [ ] Alterar tamanho de fonte impacta tipografia global visivelmente.
- [ ] Alterar densidade impacta espaçamentos/componentes elegíveis.
- [ ] High contrast altera contraste de elementos-chave.
- [ ] Reduced motion remove/reduz animações não essenciais.

---

## TASK-012-BACK-AUTH: Corrigir sessionTimeout null e contrato de resposta

### Goal

Permitir sessionTimeout = null (Nunca) de forma consistente em validação, persistência e resposta da API.

### Etapas

- [ ] Corrigir DTO/Action para não converter null em 60.
- [ ] Preservar deep-merge parcial sem sobrescrever seções não enviadas.
- [ ] Ajustar testes de feature/unit para cenário null e roundtrip.
- [ ] Revisar compatibilidade com consumidores atuais.

### Critérios de conclusão

- [ ] PATCH com null retorna null no GET subsequente.
- [ ] PATCH parcial continua preservando campos não enviados.
- [ ] Nenhum breaking change no contrato existente além da correção esperada.

---

## TASK-012-FRONT-CHAT: Conectar preferências comportamentais aos fluxos de chat

### Goal

Aplicar preferências como sound/chatNotify/quickReply/confirmBulk/ticketOpenMode em fluxos reais do chat.

### Etapas

- [ ] Mapear pontos do chat que hoje usam defaults hardcoded.
- [ ] Ler preferências do usuário e aplicar fallback seguro.
- [ ] Respeitar sound/chatNotify no disparo de sons/notificações locais.
- [ ] Aplicar confirmBulk antes de envios em massa.
- [ ] Aplicar ticketOpenMode na navegação de abertura.

### Critérios de conclusão

- [ ] Comportamentos mudam de acordo com preferências sem regressão de UX.
- [ ] Casos sem preferência persistida continuam com defaults seguros.

---

## TASK-012-TESTS: Corrigir testes quebrados e ampliar cobertura

### Goal

Garantir que os cenários críticos da feature tenham testes confiáveis e executáveis.

### Etapas

- [ ] Corrigir paths literais inválidos em TenantSettingsTest ({tenant->id} -> interpolação real).
- [ ] Ajustar assertions para envelope de resposta (success/message/data) quando aplicável.
- [ ] Validar descoberta correta de suites Vitest e adequar estrutura se necessário.
- [ ] Adicionar testes de integração frontend para fluxo de salvar preferências com notificação real.
- [ ] Adicionar testes backend para dispatch condicionado por preference.

### Critérios de conclusão

- [ ] Testes de preferências/tenant settings executam de forma determinística.
- [ ] Cobertura inclui cenário positivo, inválido e autorização.

---

## TASK-012-VALIDATION: Gates e fechamento

### Goal

Concluir a correção com evidência objetiva de qualidade e conformidade com workflow PREVC.

### Etapas

- [ ] Rodar composer gate:all no backend.
- [ ] Rodar pnpm run gate:all no frontend.
- [ ] Executar revisão QA focada em regressão de comportamento.
- [ ] Executar code review final e tratar blockers.
- [ ] Registrar evidências e status final da task.

### Critérios de conclusão

- [ ] Todos os gates verdes nas camadas afetadas.
- [ ] QA sem issues críticos.
- [ ] Review aprovado.
- [ ] Checklist da PLAN-012 atendido.
