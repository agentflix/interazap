<?php

/**
 * Teste E2E — Scheduling Tools
 * Cobre: check_availability, schedule_event
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMEvent;

require_once __DIR__.'/helpers.php';

e2e_group('07 · Scheduling Tools');

$ctx = require __DIR__.'/setup.php';

$eventId = null;

// ── check_availability ────────────────────────────────────────────────────────

e2e_run('check_availability: retorna disponível em range sem eventos', function () use ($ctx): void {
    $r = e2e_dispatch('check_availability', [
        'date_from' => '2030-12-01 09:00:00',
        'date_to' => '2030-12-01 10:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['is_available']), 'data.is_available presente');
    e2e_assert($r->data['is_available'] === true, 'is_available=true para range sem eventos');
    e2e_assert(is_array($r->data['conflicts']), 'data.conflicts é array');
});

e2e_run('check_availability: falha sem date_from', function () use ($ctx): void {
    $r = e2e_dispatch('check_availability', [
        'date_to' => '2030-12-01 10:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem date_from');
});

e2e_run('check_availability: falha com date_to anterior a date_from', function () use ($ctx): void {
    $r = e2e_dispatch('check_availability', [
        'date_from' => '2030-12-01 10:00:00',
        'date_to' => '2030-12-01 09:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para datas invertidas');
});

// ── schedule_event ─────────────────────────────────────────────────────────────

e2e_run('schedule_event: agenda reunião com contact', function () use ($ctx, &$eventId): void {
    $r = e2e_dispatch('schedule_event', [
        'title' => 'Reunião E2E',
        'type' => 'meeting',
        'starts_at' => '2030-12-01 14:00:00',
        'ends_at' => '2030-12-01 15:00:00',
        'contact_id' => $ctx['contact_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['event_id']), 'data.event_id presente');

    $eventId = $r->data['event_id'];
});

e2e_run('schedule_event: agenda evento sem contact', function () use ($ctx): void {
    $r = e2e_dispatch('schedule_event', [
        'title' => 'Evento Sem Contato E2E',
        'type' => 'call',
        'starts_at' => '2030-12-02 09:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['event_id']), 'data.event_id presente');

    // Cleanup inline
    if (isset($r->data['event_id'])) {
        CRMEvent::query()->where('id', $r->data['event_id'])->delete();
    }
});

e2e_run('schedule_event: falha sem title', function () use ($ctx): void {
    $r = e2e_dispatch('schedule_event', [
        'starts_at' => '2030-12-01 14:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem title');
});

e2e_run('check_availability: detecta conflito após agendar evento', function () use ($ctx, &$eventId): void {
    if (! $eventId) {
        // Skip se evento não foi criado
        e2e_assert(true, 'skip — evento não criado no passo anterior');

        return;
    }

    $r = e2e_dispatch('check_availability', [
        'date_from' => '2030-12-01 13:00:00',
        'date_to' => '2030-12-01 16:00:00',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert($r->data['is_available'] === false, 'is_available=false com conflito de evento');
    e2e_assert(count($r->data['conflicts']) >= 1, 'pelo menos 1 conflito detectado');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove evento criado neste grupo', function () use (&$eventId): void {
    if ($eventId) {
        CRMEvent::query()->where('id', $eventId)->delete();
    }
    e2e_assert(true, 'cleanup executado');
});
