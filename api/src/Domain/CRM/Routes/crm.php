<?php

declare(strict_types=1);

use Domain\CRM\Http\Controllers\CRMCompanyController;
use Domain\CRM\Http\Controllers\CRMContactController;
use Domain\CRM\Http\Controllers\CRMContactImportExportController;
use Domain\CRM\Http\Controllers\CRMCustomFieldController;
use Domain\CRM\Http\Controllers\CRMCustomFieldValueController;
use Domain\CRM\Http\Controllers\CRMDepartmentController;
use Domain\CRM\Http\Controllers\CRMEventController;
use Domain\CRM\Http\Controllers\CRMFunnelController;
use Domain\CRM\Http\Controllers\CRMNegotiationController;
use Domain\CRM\Http\Controllers\CRMNegotiationFileController;
use Domain\CRM\Http\Controllers\CRMNegotiationProductController;
use Domain\CRM\Http\Controllers\CRMNegotiationTaskUserController;
use Domain\CRM\Http\Controllers\CRMNoteController;
use Domain\CRM\Http\Controllers\CRMProductController;
use Domain\CRM\Http\Controllers\CRMProductListAllController;
use Domain\CRM\Http\Controllers\CRMProposalController;
use Domain\CRM\Http\Controllers\CRMReasonLossController;
use Domain\CRM\Http\Controllers\CRMTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('crm')
    ->group(function (): void {
        Route::get('/contacts', [CRMContactController::class, 'index']);
        Route::post('/contacts', [CRMContactController::class, 'store']);
        Route::post('/contacts/import/upload', [CRMContactImportExportController::class, 'upload']);
        Route::post('/contacts/import', [CRMContactImportExportController::class, 'import']);
        Route::get('/contacts/export', [CRMContactImportExportController::class, 'export']);
        Route::get('/contacts/{id}', [CRMContactController::class, 'show']);
        Route::put('/contacts/{id}', [CRMContactController::class, 'update']);
        Route::patch('/contacts/{id}', [CRMContactController::class, 'patch']);
        Route::delete('/contacts/{id}', [CRMContactController::class, 'destroy']);
        Route::post('/contacts/{id}/restore', [CRMContactController::class, 'restore']);
        Route::post('/contacts/{id}/phones', [CRMContactController::class, 'addPhone']);
        Route::post('/contacts/{id}/tags', [CRMTagController::class, 'attachToContact']);
        Route::delete('/contacts/{id}/tags/{tagId}', [CRMTagController::class, 'detachFromContact']);
        Route::post('/contacts/{id}/custom-fields', [CRMCustomFieldValueController::class, 'upsertForContact']);
        Route::get('/contacts/{id}/notes', [CRMNoteController::class, 'listForContact']);
        Route::post('/contacts/{id}/notes', [CRMNoteController::class, 'storeForContact']);

        Route::get('/companies', [CRMCompanyController::class, 'index']);
        Route::post('/companies', [CRMCompanyController::class, 'store']);
        Route::get('/companies/{id}', [CRMCompanyController::class, 'show']);
        Route::put('/companies/{id}', [CRMCompanyController::class, 'update']);
        Route::delete('/companies/{id}', [CRMCompanyController::class, 'destroy']);
        Route::post('/companies/{id}/tags', [CRMTagController::class, 'attachToCompany']);
        Route::delete('/companies/{id}/tags/{tagId}', [CRMTagController::class, 'detachFromCompany']);
        Route::post('/companies/{id}/custom-fields', [CRMCustomFieldValueController::class, 'upsertForCompany']);

        Route::get('/negotiations', [CRMNegotiationController::class, 'index']);
        Route::post('/negotiations', [CRMNegotiationController::class, 'store']);
        Route::get('/negotiations/{id}', [CRMNegotiationController::class, 'show']);
        Route::put('/negotiations/{id}', [CRMNegotiationController::class, 'update']);
        Route::delete('/negotiations/{id}', [CRMNegotiationController::class, 'destroy']);
        Route::post('/negotiations/{id}/tasks', [CRMNegotiationController::class, 'addTask']);
        Route::get('/negotiations/{id}/tasks', [CRMNegotiationController::class, 'listTasks']);
        Route::put('/negotiations/{id}/tasks/{taskId}', [CRMNegotiationController::class, 'updateTask']);
        Route::patch('/negotiations/{id}/tasks/{taskId}/status', [CRMNegotiationController::class, 'updateTaskStatus']);
        Route::patch('/negotiations/{id}/tasks/{taskId}/toggle', [CRMNegotiationController::class, 'toggleTask']);
        Route::delete('/negotiations/{id}/tasks/{taskId}', [CRMNegotiationController::class, 'deleteTask']);
        Route::post('/negotiations/{id}/tags', [CRMTagController::class, 'attachToNegotiation']);
        Route::delete('/negotiations/{id}/tags/{tagId}', [CRMTagController::class, 'detachFromNegotiation']);
        Route::get('/negotiations/{id}/proposals', [CRMProposalController::class, 'index']);
        Route::post('/negotiations/{id}/proposals', [CRMProposalController::class, 'store']);
        Route::get('/negotiations/{id}/products', [CRMNegotiationProductController::class, 'index']);
        Route::post('/negotiations/{id}/products', [CRMNegotiationProductController::class, 'store']);
        Route::post('/negotiations/{id}/move', [CRMNegotiationController::class, 'move']);
        Route::post('/negotiations/reorder', [CRMNegotiationController::class, 'reorder']);
        Route::post('/negotiations/{id}/won', [CRMNegotiationController::class, 'markWon']);
        Route::post('/negotiations/{id}/lost', [CRMNegotiationController::class, 'markLost']);
        Route::post('/negotiations/{id}/reopen', [CRMNegotiationController::class, 'reopen']);
        Route::get('/negotiations-kanban/step/{stepId}', [CRMNegotiationController::class, 'kanbanStep']);
        Route::get('/negotiations-kanban', [CRMNegotiationController::class, 'kanban']);
        Route::post('/negotiations/{id}/custom-fields', [CRMCustomFieldValueController::class, 'upsertForNegotiation']);
        Route::get('/negotiations/{id}/notes', [CRMNoteController::class, 'listForNegotiation']);
        Route::post('/negotiations/{id}/notes', [CRMNoteController::class, 'storeForNegotiation']);
        Route::get('/negotiations/{id}/files', [CRMNegotiationFileController::class, 'index']);
        Route::post('/negotiations/{id}/files', [CRMNegotiationFileController::class, 'store']);
        Route::delete('/negotiations/{id}/files/{fileId}', [CRMNegotiationFileController::class, 'destroy']);
        Route::get('/negotiations/tasks/user', CRMNegotiationTaskUserController::class);

        Route::get('/tags', [CRMTagController::class, 'index']);
        Route::get('/tags/all', [CRMTagController::class, 'all']);
        Route::post('/tags', [CRMTagController::class, 'store']);
        Route::get('/tags/{id}', [CRMTagController::class, 'show']);
        Route::put('/tags/{id}', [CRMTagController::class, 'update']);
        Route::delete('/tags/{id}', [CRMTagController::class, 'destroy']);

        Route::get('/reason-losses', [CRMReasonLossController::class, 'index']);
        Route::get('/reason-losses/all', [CRMReasonLossController::class, 'all']);
        Route::post('/reason-losses', [CRMReasonLossController::class, 'store']);
        Route::put('/reason-losses/{id}', [CRMReasonLossController::class, 'update']);
        Route::delete('/reason-losses/{id}', [CRMReasonLossController::class, 'destroy']);

        Route::get('/funnels', [CRMFunnelController::class, 'index']);
        Route::get('/funnels/all', [CRMFunnelController::class, 'all']);
        Route::post('/funnels', [CRMFunnelController::class, 'store']);
        Route::put('/funnels/{id}', [CRMFunnelController::class, 'update']);
        Route::delete('/funnels/{id}', [CRMFunnelController::class, 'destroy']);
        Route::get('/funnels/{id}/steps', [CRMFunnelController::class, 'steps']);
        Route::post('/funnels/{id}/steps', [CRMFunnelController::class, 'addStep']);
        Route::put('/funnels/{id}/steps/{stepId}', [CRMFunnelController::class, 'updateStep']);
        Route::delete('/funnels/{id}/steps/{stepId}', [CRMFunnelController::class, 'destroyStep']);
        Route::post('/funnels/{id}/steps/reorder', [CRMFunnelController::class, 'reorder']);

        Route::get('/products', [CRMProductController::class, 'index']);
        Route::post('/products', [CRMProductController::class, 'store']);
        Route::get('/products/{id}', [CRMProductController::class, 'show']);
        Route::put('/products/{id}', [CRMProductController::class, 'update']);
        Route::delete('/products/{id}', [CRMProductController::class, 'destroy']);
        Route::get('/products-all', CRMProductListAllController::class);

        Route::get('/proposals/{id}', [CRMProposalController::class, 'show']);
        Route::put('/proposals/{id}', [CRMProposalController::class, 'update']);
        Route::delete('/proposals/{id}', [CRMProposalController::class, 'destroy']);
        Route::post('/proposals/{id}/send', [CRMProposalController::class, 'send']);
        Route::post('/proposals/{id}/duplicate', [CRMProposalController::class, 'duplicate']);
        Route::put('/negotiation-products/{itemId}', [CRMNegotiationProductController::class, 'update']);
        Route::delete('/negotiation-products/{itemId}', [CRMNegotiationProductController::class, 'destroy']);

        Route::get('/custom-fields', [CRMCustomFieldController::class, 'index']);
        Route::post('/custom-fields', [CRMCustomFieldController::class, 'store']);
        Route::put('/custom-fields/{id}', [CRMCustomFieldController::class, 'update']);
        Route::delete('/custom-fields/{id}', [CRMCustomFieldController::class, 'destroy']);

        Route::get('/departments', [CRMDepartmentController::class, 'index']);
        Route::get('/departments/all', [CRMDepartmentController::class, 'all']);
        Route::post('/departments', [CRMDepartmentController::class, 'store']);
        Route::get('/departments/{id}', [CRMDepartmentController::class, 'show']);
        Route::put('/departments/{id}', [CRMDepartmentController::class, 'update']);
        Route::delete('/departments/{id}', [CRMDepartmentController::class, 'destroy']);
        Route::patch('/departments/{id}/toggle-active', [CRMDepartmentController::class, 'toggleActive']);

        Route::prefix('/events')->group(function (): void {
            Route::get('/', [CRMEventController::class, 'index']);
            Route::get('/calendar', [CRMEventController::class, 'calendar']);
            Route::get('/upcoming', [CRMEventController::class, 'upcoming']);
            Route::get('/statistics', [CRMEventController::class, 'statistics']);
            Route::get('/linked/{type}/{id}', [CRMEventController::class, 'linked']);
            Route::get('/{id}', [CRMEventController::class, 'show']);
            Route::post('/', [CRMEventController::class, 'store']);
            Route::put('/{id}', [CRMEventController::class, 'update']);
            Route::patch('/{id}/status', [CRMEventController::class, 'updateStatus']);
            Route::delete('/{id}', [CRMEventController::class, 'destroy']);
        });
    });

// Rotas públicas de proposta — protegidas pelo limiter 'public' (30 req/min por IP).
// API-SEC-006: Alinhado ao RateLimiter::for('public') do AppServiceProvider.
Route::middleware(['throttle:public'])->group(function (): void {
    Route::get('/crm/proposals/view/{token}', [CRMProposalController::class, 'publicView']);
    Route::post('/crm/proposals/{token}/accept', [CRMProposalController::class, 'publicAccept']);
    Route::post('/crm/proposals/{token}/reject', [CRMProposalController::class, 'publicReject']);
});
