<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration unificada de índices de performance para o InteraZap.
 *
 * Centraliza todos os índices críticos de leitura (filtros, buscas,
 * ordenação, paginação, busca vetorial e full-text) em um único arquivo,
 * garantindo idempotência via `CREATE INDEX IF NOT EXISTS`.
 * Índices parciais, expression, GIN e HNSW são criados via DB::statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Acelera filtros e relatórios por status de cobrança do tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_platform_tenants_billing_status ON platform_tenants(billing_status)");

        // 2. Otimiza listagens de tenants ativos por plano (ex: contagem de assinantes).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_platform_tenants_plan_active ON platform_tenants(plan_id, is_active)");

        // 3. Acelera listagens e filtros de usuários ativos dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_auth_users_tenant_active ON auth_users(tenant_id, is_active)");

        // 4. Otimiza busca única por e-mail (login, convites, validações).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_auth_users_email ON auth_users(email)");

        // 5. Acelera lookups de tokens pessoais pelo model polimórfico dono.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_auth_personal_access_tokens_tokenable ON auth_personal_access_tokens(tokenable_type, tokenable_id)");

        // 6. Acelera listagens de empresas ativas dentro de um tenant (CRM).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_companies_tenant_active ON crm_companies(tenant_id, is_active)");

        // 7. Otimiza busca por CNPJ/CPF e previne duplicatas.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_companies_document ON crm_companies(document)");

        // 8. Acelera listagens de contatos ativos dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_contacts_tenant_active ON crm_contacts(tenant_id, is_active)");

        // 9. Otimiza carregamento de contatos vinculados a uma empresa.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_contacts_company ON crm_contacts(crm_company_id)");

        // 10. Acelera filtros de negociações por status dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_negotiations_tenant_status ON crm_negotiations(tenant_id, status)");

        // 11. Otimiza agrupamento por funil e etapa (pipelines Kanban).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_negotiations_funnel_step ON crm_negotiations(crm_negotiation_funnel_id, crm_negotiation_funnel_step_id)");

        // 12. Acelera filtros de negociações por responsável (atendente).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_negotiations_user ON crm_negotiations(auth_user_id)");

        // 13. Índice composto para cursor pagination no Kanban (tenant + funil + posição + status).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_negotiations_kanban_cursor ON crm_negotiations(tenant_id, crm_negotiation_funnel_id, position, status)");

        // 14. Acelera listagens de instâncias de chat por tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_instances_tenant ON chat_instances(tenant_id)");

        // 15. Índice expression para busca rápida do token dentro do JSON de settings.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_instances_settings_token ON chat_instances((settings_json->>'token'))");

        // 16. [REMOVIDO] Índice parcial para phone_number_id removido — campo não existe no schema.

        // 17. Acelera listagens de tickets por status dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_tenant_status ON chat_tickets(tenant_id, status)");

        // 18. Otimiza carregamento de tickets vinculados a uma instância.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_instance ON chat_tickets(instance_id)");

        // 19. Acelera filtros de tickets por atendente responsável.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_assigned ON chat_tickets(assigned_to)");

        // 20. Otimiza busca por protocolo único de atendimento.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_protocol ON chat_tickets(protocol)");

        // 21. Acelera ordenação e filtros por última mensagem do cliente.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_last_customer ON chat_tickets(last_customer_message_at)");

        // 22. Acelera ordenação e filtros por última mensagem do agente.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_last_agent ON chat_tickets(last_agent_message_at)");

        // 23. Otimiza filtros e relatórios por sentimento e score.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_tickets_sentiment ON chat_tickets(sentiment, sentiment_score)");

        // 24. Acelera carregamento de mensagens de um ticket (evita N+1).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_messages_ticket ON chat_messages(ticket_id)");

        // 25. Otimiza busca por ID externo (deduplicação e sincronização).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_messages_external ON chat_messages(external_id)");

        // 26. Acelera listagens e filtros de mensagens por tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_messages_tenant ON chat_messages(tenant_id)");

        // 27. Acelera filtros e relatórios de faturas por status dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_billing_invoices_tenant_status ON billing_invoices(tenant_id, status)");

        // 28. Otimiza ordenação e alertas por data de vencimento.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_billing_invoices_due_date ON billing_invoices(due_date)");

        // 29. Acelera lookups de auditoria pelo model polimórfico auditable.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_audit_logs_auditable ON audit_logs(auditable_type, auditable_id)");

        // 30. Otimiza filtros de logs de auditoria por evento dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_audit_logs_tenant_event ON audit_logs(tenant_id, event)");

        // 31. Índice GIN para busca eficiente dentro do JSONB old_values.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_audit_logs_old_values_gin ON audit_logs USING gin(old_values)");

        // 32. Índice GIN para busca eficiente dentro do JSONB new_values.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_audit_logs_new_values_gin ON audit_logs USING gin(new_values)");

        // 33. Acelera relatórios de uso de IA por feature dentro de um tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_usage_logs_tenant_feature ON ai_usage_logs(tenant_id, feature)");

        // 34. Otimiza análises de custo e consumo por modelo de IA.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_usage_logs_model ON ai_usage_logs(ai_model_pricing_id)");

        // 35. Acelera carregamento de chunks vinculados a um documento (RAG).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunks_document ON ai_knowledge_chunks(document_id)");

        // 36. Índice HNSW para busca por similaridade vetorial (cosine) nos embeddings.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunks_embedding_hnsw ON ai_knowledge_chunks USING hnsw(embedding vector_cosine_ops)");

        // 37. Índice GIN para full-text search no campo content_tsv.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunks_tsv_gin ON ai_knowledge_chunks USING gin(content_tsv)");

        // 38. Acelera listagens de referências de chunks por tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunk_refs_tenant ON ai_knowledge_chunk_refs(tenant_id)");

        // 39. Acelera listagens e filtros de logs de queries RAG por tenant.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_ai_rag_query_logs_tenant ON ai_rag_query_logs(tenant_id)");

        // 40. Otimiza busca de plano por slug (ex: lookup em tempo de execução).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_platform_plans_slug ON platform_plans(slug)");

        // 41. Acelera filtros de planos por modo de relatórios habilitado.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_platform_plans_reports_mode ON platform_plans(reports_mode)");

        // 42. Acelera lookups de dispositivos vinculados a um usuário.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_auth_device_tokens_user ON auth_device_tokens(user_id)");

        // 43. Otimiza busca e validação de token de dispositivo.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_auth_device_tokens_token ON auth_device_tokens(token)");

        // 44. Índice composto para consultas de eventos CRM por tenant e intervalo de datas.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crm_events_tenant_dates ON crm_events(tenant_id, starts_at, ends_at)");

        // 45. Acelera carregamento de templates vinculados a uma instância de chat.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_message_templates_instance ON chat_message_templates(chat_instance_id)");

        // 46. Otimiza busca de templates por ID externo (sincronização).
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_message_templates_external ON chat_message_templates(external_id)");

        // 47. Otimiza busca e validação de sessão por token.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_sessions_token ON chat_sessions(token)");

        // 48. Acelera carregamento de sessões vinculadas a um ticket.
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_sessions_ticket ON chat_sessions(ticket_id)");
    }

    public function down(): void
    {
        // 48. Drop de sessões por ticket.
        DB::statement("DROP INDEX IF EXISTS idx_chat_sessions_ticket");

        // 47. Drop de sessões por token.
        DB::statement("DROP INDEX IF EXISTS idx_chat_sessions_token");

        // 46. Drop de templates por ID externo.
        DB::statement("DROP INDEX IF EXISTS idx_chat_message_templates_external");

        // 45. Drop de templates por instância.
        DB::statement("DROP INDEX IF EXISTS idx_chat_message_templates_instance");

        // 44. Drop de eventos CRM por tenant e datas.
        DB::statement("DROP INDEX IF EXISTS idx_crm_events_tenant_dates");

        // 43. Drop de tokens de dispositivo.
        DB::statement("DROP INDEX IF EXISTS idx_auth_device_tokens_token");

        // 42. Drop de dispositivos por usuário.
        DB::statement("DROP INDEX IF EXISTS idx_auth_device_tokens_user");

        // 41. Drop de planos por modo de relatórios.
        DB::statement("DROP INDEX IF EXISTS idx_platform_plans_reports_mode");

        // 40. Drop de planos por slug.
        DB::statement("DROP INDEX IF EXISTS idx_platform_plans_slug");

        // 39. Drop de logs RAG por tenant.
        DB::statement("DROP INDEX IF EXISTS idx_ai_rag_query_logs_tenant");

        // 38. Drop de referências de chunks por tenant.
        DB::statement("DROP INDEX IF EXISTS idx_ai_knowledge_chunk_refs_tenant");

        // 37. Drop de GIN full-text search em knowledge chunks.
        DB::statement("DROP INDEX IF EXISTS idx_ai_knowledge_chunks_tsv_gin");

        // 36. Drop de índice HNSW para embeddings.
        DB::statement("DROP INDEX IF EXISTS idx_ai_knowledge_chunks_embedding_hnsw");

        // 35. Drop de chunks por documento.
        DB::statement("DROP INDEX IF EXISTS idx_ai_knowledge_chunks_document");

        // 34. Drop de logs de uso por modelo de IA.
        DB::statement("DROP INDEX IF EXISTS idx_ai_usage_logs_model");

        // 33. Drop de logs de uso por tenant e feature.
        DB::statement("DROP INDEX IF EXISTS idx_ai_usage_logs_tenant_feature");

        // 32. Drop de GIN em new_values.
        DB::statement("DROP INDEX IF EXISTS idx_audit_logs_new_values_gin");

        // 31. Drop de GIN em old_values.
        DB::statement("DROP INDEX IF EXISTS idx_audit_logs_old_values_gin");

        // 30. Drop de logs de auditoria por tenant e evento.
        DB::statement("DROP INDEX IF EXISTS idx_audit_logs_tenant_event");

        // 29. Drop de logs de auditoria por entidade auditable.
        DB::statement("DROP INDEX IF EXISTS idx_audit_logs_auditable");

        // 28. Drop de faturas por data de vencimento.
        DB::statement("DROP INDEX IF EXISTS idx_billing_invoices_due_date");

        // 27. Drop de faturas por tenant e status.
        DB::statement("DROP INDEX IF EXISTS idx_billing_invoices_tenant_status");

        // 26. Drop de mensagens por tenant.
        DB::statement("DROP INDEX IF EXISTS idx_chat_messages_tenant");

        // 25. Drop de mensagens por ID externo.
        DB::statement("DROP INDEX IF EXISTS idx_chat_messages_external");

        // 24. Drop de mensagens por ticket.
        DB::statement("DROP INDEX IF EXISTS idx_chat_messages_ticket");

        // 23. Drop de tickets por sentimento e score.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_sentiment");

        // 22. Drop de tickets por última mensagem do agente.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_last_agent");

        // 21. Drop de tickets por última mensagem do cliente.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_last_customer");

        // 20. Drop de tickets por protocolo.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_protocol");

        // 19. Drop de tickets por atendente responsável.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_assigned");

        // 18. Drop de tickets por instância.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_instance");

        // 17. Drop de tickets por tenant e status.
        DB::statement("DROP INDEX IF EXISTS idx_chat_tickets_tenant_status");

        // 16. Drop de índice parcial de instâncias Meta.
        // idx_chat_instances_phone_number_id removido — campo não existe no schema

        // 15. Drop de índice expression em settings_json.
        DB::statement("DROP INDEX IF EXISTS idx_chat_instances_settings_token");

        // 14. Drop de instâncias por tenant.
        DB::statement("DROP INDEX IF EXISTS idx_chat_instances_tenant");

        // 13. Drop de índice composto para cursor pagination no Kanban.
        DB::statement("DROP INDEX IF EXISTS idx_crm_negotiations_kanban_cursor");

        // 12. Drop de negociações por usuário.
        DB::statement("DROP INDEX IF EXISTS idx_crm_negotiations_user");

        // 11. Drop de negociações por funil e etapa.
        DB::statement("DROP INDEX IF EXISTS idx_crm_negotiations_funnel_step");

        // 10. Drop de negociações por tenant e status.
        DB::statement("DROP INDEX IF EXISTS idx_crm_negotiations_tenant_status");

        // 9. Drop de contatos por empresa.
        DB::statement("DROP INDEX IF EXISTS idx_crm_contacts_company");

        // 8. Drop de contatos por tenant e ativo.
        DB::statement("DROP INDEX IF EXISTS idx_crm_contacts_tenant_active");

        // 7. Drop de empresas por documento.
        DB::statement("DROP INDEX IF EXISTS idx_crm_companies_document");

        // 6. Drop de empresas por tenant e ativo.
        DB::statement("DROP INDEX IF EXISTS idx_crm_companies_tenant_active");

        // 5. Drop de tokens pessoais por entidade polimórfica.
        DB::statement("DROP INDEX IF EXISTS idx_auth_personal_access_tokens_tokenable");

        // 4. Drop de usuários por e-mail.
        DB::statement("DROP INDEX IF EXISTS idx_auth_users_email");

        // 3. Drop de usuários por tenant e ativo.
        DB::statement("DROP INDEX IF EXISTS idx_auth_users_tenant_active");

        // 2. Drop de tenants por plano e ativo.
        DB::statement("DROP INDEX IF EXISTS idx_platform_tenants_plan_active");

        // 1. Drop de tenants por status de cobrança.
        DB::statement("DROP INDEX IF EXISTS idx_platform_tenants_billing_status");
    }
};
