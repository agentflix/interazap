<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiNotificationActions;
use Domain\Ai\Http\Requests\AiNotificationMarkAsReadRequest;
use Domain\Ai\Http\Resources\AiNotificationResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for AI Seller Notification endpoints.
 *
 * Manages notifications sent to sellers from AI agents.
 */
final class AiNotificationController extends BaseController
{
    public function __construct(private readonly AiNotificationActions $actions) {}

    /**
     * List notifications for current user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $unreadOnly = $request->boolean('unread_only', false);

        $notifications = $this->actions->list(
            tenantId: $tenantId,
            userId: $userId,
            unreadOnly: $unreadOnly,
        );

        return AiNotificationResource::collection($notifications);
    }

    /**
     * Get a specific notification.
     */
    public function show(Request $request, string $id): AiNotificationResource|JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $notification = $this->actions->find($tenantId, $userId, $id);

        if (! $notification) {
            return response()->json([
                'message' => 'Notification not found.',
            ], 404);
        }

        return new AiNotificationResource($notification);
    }

    /**
     * Mark notification(s) as read.
     */
    public function markAsRead(AiNotificationMarkAsReadRequest $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $ids = $request->validated('ids');

        $updated = $this->actions->markAsRead($tenantId, $userId, $ids);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notification(s) marked as read.",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $userId = $request->user()->id;

        $count = $this->actions->unreadCount($tenantId, $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}
