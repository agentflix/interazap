<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona foreign keys cross-domain que não puderam ser criadas
 * nas migrations iniciais devido à ordem de dependência entre tabelas.
 *
 * FKs adicionadas:
 * - platform_tenants.segment_id -> ai_prompt_segments
 * - chat_tickets.current_ai_agent_id -> ai_agents
 * - ai_post_sale_schedules.message_id -> chat_messages
 * - ai_autopilot_runs.parent_run_id -> ai_autopilot_runs (self-reference)
 * - ai_agents.parent_agent_id -> ai_agents (self-reference)
 */
return new class extends Migration
{
    public function up(): void
    {
        // platform_tenants.segment_id -> ai_prompt_segments
        if (Schema::hasColumn('platform_tenants', 'segment_id')) {
            $constraintExists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_name = 'platform_tenants'
                  AND constraint_name = 'platform_tenants_segment_id_foreign'
                  AND constraint_type = 'FOREIGN KEY'
            ");
            if (! $constraintExists) {
                DB::statement("
                    ALTER TABLE platform_tenants
                    ADD CONSTRAINT platform_tenants_segment_id_foreign
                    FOREIGN KEY (segment_id) REFERENCES ai_prompt_segments(id) ON DELETE SET NULL
                ");
            }
        }

        // chat_tickets.current_ai_agent_id -> ai_agents
        if (Schema::hasColumn('chat_tickets', 'current_ai_agent_id')) {
            $constraintExists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_name = 'chat_tickets'
                  AND constraint_name = 'fk_chat_tickets_current_ai_agent_id'
                  AND constraint_type = 'FOREIGN KEY'
            ");
            if (! $constraintExists) {
                DB::statement("
                    ALTER TABLE chat_tickets
                    ADD CONSTRAINT fk_chat_tickets_current_ai_agent_id
                    FOREIGN KEY (current_ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
                ");
            }
        }

        // ai_post_sale_schedules.message_id -> chat_messages
        // [REMOVIDO] message_id agora é varchar(100), não uuid — FK inválida

        // ai_autopilot_runs.parent_run_id -> ai_autopilot_runs (self-reference)
        if (Schema::hasColumn('ai_autopilot_runs', 'parent_run_id')) {
            $constraintExists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_name = 'ai_autopilot_runs'
                  AND constraint_name = 'fk_ai_autopilot_runs_parent_run_id'
                  AND constraint_type = 'FOREIGN KEY'
            ");
            if (! $constraintExists) {
                DB::statement("
                    ALTER TABLE ai_autopilot_runs
                    ADD CONSTRAINT fk_ai_autopilot_runs_parent_run_id
                    FOREIGN KEY (parent_run_id) REFERENCES ai_autopilot_runs(id) ON DELETE SET NULL
                ");
            }
        }

        // ai_agents.parent_agent_id -> ai_agents (self-reference)
        if (Schema::hasColumn('ai_agents', 'parent_agent_id')) {
            $constraintExists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE table_name = 'ai_agents'
                  AND constraint_name = 'fk_ai_agents_parent_agent_id'
                  AND constraint_type = 'FOREIGN KEY'
            ");
            if (! $constraintExists) {
                DB::statement("
                    ALTER TABLE ai_agents
                    ADD CONSTRAINT fk_ai_agents_parent_agent_id
                    FOREIGN KEY (parent_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
                ");
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ai_agents DROP CONSTRAINT IF EXISTS fk_ai_agents_parent_agent_id');
        DB::statement('ALTER TABLE ai_autopilot_runs DROP CONSTRAINT IF EXISTS fk_ai_autopilot_runs_parent_run_id');
        // fk_ai_post_sale_schedules_message_id removido — message_id é varchar, não uuid
        DB::statement('ALTER TABLE chat_tickets DROP CONSTRAINT IF EXISTS fk_chat_tickets_current_ai_agent_id');
        DB::statement('ALTER TABLE platform_tenants DROP CONSTRAINT IF EXISTS platform_tenants_segment_id_foreign');
    }
};
