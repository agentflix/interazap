<?php

declare(strict_types=1);

use Domain\Ai\Http\Controllers\AiAgentController;
use Domain\Ai\Http\Controllers\AiAutopilotRunController;
use Domain\Ai\Http\Controllers\AiBudgetController;
use Domain\Ai\Http\Controllers\AiPromptMasterController;
use Domain\Ai\Http\Controllers\AiPromptPlanController;
use Domain\Ai\Http\Controllers\AiPromptQuarantineController;
use Domain\Ai\Http\Controllers\AiPromptSegmentController;
use Domain\Ai\Http\Controllers\AiPromptTenantController;
use Domain\Ai\Http\Controllers\AiTenantSegmentController;
use Domain\Ai\Http\Controllers\InternalAiController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// TENANT AI ROUTES
// Protection: auth:sanctum (Sanctum token required)
// Tenant isolation: enforced per-controller via $request->user()->tenant_id
// Authorization: each action calls $this->authorize() with a Spatie permission
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('ai')->group(function (): void {
    Route::get('agents', [AiAgentController::class, 'index']);
    Route::post('agents', [AiAgentController::class, 'store']);
    Route::get('agents/{id}', [AiAgentController::class, 'show']);
    Route::put('agents/{id}', [AiAgentController::class, 'update']);
    Route::delete('agents/{id}', [AiAgentController::class, 'destroy']);
    Route::patch('agents/{id}/toggle', [AiAgentController::class, 'toggle']);
    Route::get('agents/{id}/files', [AiAgentController::class, 'files']);
    Route::get('agents/{id}/files/{slug}', [AiAgentController::class, 'fileShow']);
    Route::put('agents/{id}/files/{slug}', [AiAgentController::class, 'fileUpdate']);
    Route::get('tools/catalog', [AiAgentController::class, 'toolsCatalog']);
    Route::get('tools/presets/{role}', [AiAgentController::class, 'toolsPreset']);
    Route::get('agents/{id}/tools', [AiAgentController::class, 'tools']);
    Route::put('agents/{id}/tools', [AiAgentController::class, 'toolsUpdate']);
    Route::get('agents/{id}/triggers', [AiAgentController::class, 'triggers']);
    Route::post('agents/{id}/triggers', [AiAgentController::class, 'triggerStore']);
    Route::put('agents/{id}/triggers/{triggerId}', [AiAgentController::class, 'triggerUpdate']);
    Route::delete('agents/{id}/triggers/{triggerId}', [AiAgentController::class, 'triggerDestroy']);
    Route::get('agents/{id}/skills', [AiAgentController::class, 'skills']);
    Route::post('agents/{id}/skills', [AiAgentController::class, 'skillStore']);
    Route::put('agents/{id}/skills/{skillId}', [AiAgentController::class, 'skillUpdate']);
    Route::delete('agents/{id}/skills/{skillId}', [AiAgentController::class, 'skillDestroy']);
    Route::get('agents/{id}/channels', [AiAgentController::class, 'channels']);
    Route::post('agents/{id}/channels', [AiAgentController::class, 'channelStore']);
    Route::put('agents/{id}/channels/{channelId}', [AiAgentController::class, 'channelUpdate']);
    Route::delete('agents/{id}/channels/{channelId}', [AiAgentController::class, 'channelDestroy']);
    Route::get('agents/{id}/delegations', [AiAgentController::class, 'delegations']);
    Route::post('agents/{id}/delegations', [AiAgentController::class, 'delegationStore']);
    Route::delete('agents/{id}/delegations/{delegationId}', [AiAgentController::class, 'delegationDestroy']);
    Route::get('agents/{id}/voice', [AiAgentController::class, 'voice']);
    Route::put('agents/{id}/voice', [AiAgentController::class, 'voiceUpdate']);

    Route::get('budget', [AiBudgetController::class, 'show']);
    Route::put('budget', [AiBudgetController::class, 'update']);

    Route::get('runs', [AiAutopilotRunController::class, 'index']);
    Route::post('runs', [AiAutopilotRunController::class, 'store']);
    Route::post('playbooks/{playbookId}/runs', [AiAutopilotRunController::class, 'runPlaybook']);
    Route::get('runs/{id}', [AiAutopilotRunController::class, 'show']);
    Route::patch('runs/{id}/cancel', [AiAutopilotRunController::class, 'cancel']);

    // Prompt Management - Tenant Routes
    Route::get('prompt', [AiPromptTenantController::class, 'show'])->name('ai.prompt.show');
    Route::put('prompt', [AiPromptTenantController::class, 'update'])->name('ai.prompt.update');
    Route::delete('prompt', [AiPromptTenantController::class, 'destroy'])->name('ai.prompt.destroy');
    Route::post('prompt/rollback', [AiPromptTenantController::class, 'rollback'])->name('ai.prompt.rollback');
    Route::get('prompt/resolve', [AiPromptTenantController::class, 'resolve'])->name('ai.prompt.resolve');
});

// ─────────────────────────────────────────────────────────────────────────────
// SUPER ADMIN — PROMPT GOVERNANCE ROUTES
// Protection: auth:sanctum + permission:ai.prompts.manage (Spatie gate)
// Only platform-level admins (super_admin role) may access these routes.
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'permission:ai.prompts.manage'])
    ->prefix('platform/ai/prompts')
    ->group(function (): void {
        // Masters
        Route::get('/masters', [AiPromptMasterController::class, 'index'])->name('platform.ai.masters.index');
        Route::post('/masters', [AiPromptMasterController::class, 'store'])->name('platform.ai.masters.store');
        Route::get('/masters/{master}', [AiPromptMasterController::class, 'show'])->name('platform.ai.masters.show');
        Route::put('/masters/{master}', [AiPromptMasterController::class, 'update'])->name('platform.ai.masters.update');
        Route::delete('/masters/{master}', [AiPromptMasterController::class, 'destroy'])->name('platform.ai.masters.destroy');

        // Segments
        Route::get('/segments', [AiPromptSegmentController::class, 'index'])->name('platform.ai.segments.index');
        Route::post('/segments', [AiPromptSegmentController::class, 'store'])->name('platform.ai.segments.store');
        Route::get('/segments/{segment}', [AiPromptSegmentController::class, 'show'])->name('platform.ai.segments.show');
        Route::put('/segments/{segment}', [AiPromptSegmentController::class, 'update'])->name('platform.ai.segments.update');
        Route::delete('/segments/{segment}', [AiPromptSegmentController::class, 'destroy'])->name('platform.ai.segments.destroy');

        // Plans
        Route::get('/plans', [AiPromptPlanController::class, 'index'])->name('platform.ai.plans.index');
        Route::get('/plans/{plan}', [AiPromptPlanController::class, 'show'])->name('platform.ai.plans.show');
        Route::put('/plans/{plan}', [AiPromptPlanController::class, 'update'])->name('platform.ai.plans.update');

        // Quarantine
        Route::get('/quarantine', [AiPromptQuarantineController::class, 'index'])->name('platform.ai.quarantine.index');
        Route::get('/quarantine/{prompt}', [AiPromptQuarantineController::class, 'show'])->name('platform.ai.quarantine.show');
        Route::post('/quarantine/{prompt}/approve', [AiPromptQuarantineController::class, 'approve'])->name('platform.ai.quarantine.approve');
        Route::post('/quarantine/{prompt}/reject', [AiPromptQuarantineController::class, 'reject'])->name('platform.ai.quarantine.reject');
    });

// ─────────────────────────────────────────────────────────────────────────────
// SUPER ADMIN — TENANT SEGMENT ASSIGNMENT
// Protection: auth:sanctum + permission:ai.prompts.manage
// ─────────────────────────────────────────────────────────────────────────────
// SuperAdmin - Assign segment to tenant
Route::middleware(['auth:sanctum', 'permission:ai.prompts.manage'])
    ->put('/platform/tenants/{tenant}/segment', [AiTenantSegmentController::class, 'update'])
    ->name('platform.tenants.segment.update');

// ─────────────────────────────────────────────────────────────────────────────
// INTERNAL GATEWAY ROUTES
// Protection: internal.api.key middleware (shared secret between API and Gateway)
// These routes are NOT exposed to the public internet; they are only reachable
// from the Gateway service within the same Docker network.
// No auth:sanctum here — the Gateway authenticates via the shared API key.
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['internal.api.key'])->prefix('internal/ai')->group(function (): void {
    Route::get('agents/available', [InternalAiController::class, 'availableAgents']);
    Route::get('context/{ticketId}', [InternalAiController::class, 'context']);
    Route::get('prompt/{tenantId}', [InternalAiController::class, 'prompt']);
    Route::get('tools/{agentId}', [InternalAiController::class, 'tools']);
    Route::get('runs/{runId}', [InternalAiController::class, 'runStatus']);
    Route::post('runs/delegate', [InternalAiController::class, 'delegateRun']);
    Route::post('tool/{toolName}', [InternalAiController::class, 'executeTool']);
    Route::get('knowledge/search', [InternalAiController::class, 'knowledgeSearch']);
});
