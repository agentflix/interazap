<?php

/**
 * Teste E2E — Notify Seller Tool
 * Cobre: notify_seller
 */

declare(strict_types=1);

use Domain\Ai\Models\AiSellerNotification;
use Illuminate\Support\Str;

require_once __DIR__ . '/helpers.php';

e2e_group('09 · Notify Seller Tool');

$ctx = require __DIR__ . '/setup.php';

// seller_id precisa ser um auth_user real (FK constraint)
$sellerId = (string) (\Domain\Auth\Models\AuthUser::query()->value('id') ?? Str::orderedUuid());

$notificationId = null;

// ── notify_seller ─────────────────────────────────────────────────────────────

e2e_run('notify_seller: cria notificação persistida com sucesso', function () use ($ctx, $sellerId, &$notificationId): void {
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $sellerId,
        'message'   => '[E2E] Lead qualificado requer atenção imediata.',
        'reason'    => 'lead_qualified',
        'channel'   => 'email',
        'priority'  => 'high',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['notification_id']), 'data.notification_id presente');
    e2e_assert($r->data['seller_id'] === $sellerId, 'data.seller_id correto');
    e2e_assert($r->data['status'] === 'pending', "status=pending (got: {$r->data['status']})");

    $notificationId = $r->data['notification_id'];

    // Verifica persistência no banco
    $notif = AiSellerNotification::find($notificationId);
    e2e_assert($notif !== null, 'notificação persistida no banco');
    e2e_assert($notif->tenant_id === $ctx['tenant_id'], 'tenant_id correto no banco');
});

e2e_run('notify_seller: cria notificação via whatsapp', function () use ($ctx, $sellerId): void {
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $sellerId,
        'message'   => '[E2E] Notificação via WhatsApp.',
        'reason'    => 'general',
        'channel'   => 'whatsapp',
        'priority'  => 'normal',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert($r->data['channel'] === 'whatsapp', "channel=whatsapp (got: {$r->data['channel']})");

    // Cleanup inline
    if (isset($r->data['notification_id'])) {
        AiSellerNotification::query()->where('id', $r->data['notification_id'])->delete();
    }
});

e2e_run('notify_seller: falha sem seller_id', function () use ($ctx): void {
    $r = e2e_dispatch('notify_seller', [
        'message' => 'Mensagem sem vendedor',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem seller_id');
});

e2e_run('notify_seller: falha com message vazia', function () use ($ctx, $sellerId): void {
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $sellerId,
        'message'   => '',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false com message vazia');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove notificações criadas neste grupo', function () use ($ctx, &$notificationId): void {
    if ($notificationId) {
        AiSellerNotification::query()->where('id', $notificationId)->delete();
    }
    // Remove todas do tenant E2E para limpar qualquer rastro
    AiSellerNotification::query()->where('tenant_id', $ctx['tenant_id'])->delete();
    e2e_assert(true, 'cleanup executado');
});
