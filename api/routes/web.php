<?php

use Domain\Chat\Http\Controllers\ChatTicketEvaluationPublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View => view('welcome'));
Route::middleware(['throttle:public'])->group(function (): void {
    Route::get('/chat/evaluations/{token}', ChatTicketEvaluationPublicPageController::class)
        ->name('chat.evaluations.public.page');
    Route::get('/public/chat/evaluations/{token}', ChatTicketEvaluationPublicPageController::class);
});
