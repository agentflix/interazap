# MEMORY.md

- Webchat público: evitar parsing manual legado de `window.location.pathname` para tenant; priorizar `ActivatedRoute` e repasse explícito de `tenantId` para componentes filhos.
- Em correções de integração frontend-backend, validar contrato real do endpoint (campos `tenant_id`, `visitor_name`, `visitor_phone`) antes de manter headers legados (`X-Tenant-Slug`).
- Para websocket entre serviços, sempre validar compatibilidade JWT em 2 eixos: segredo de assinatura compartilhado e claims obrigatórias do gateway (`sub` + `tenant_id`).
