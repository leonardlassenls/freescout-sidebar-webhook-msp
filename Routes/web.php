<?php

use Illuminate\Support\Facades\Route;
use Modules\SidebarWebhook\Http\Controllers\SidebarWebhookController;

Route::post('/sidebar/action', [
    SidebarWebhookController::class,
    'handleAction'
])->name('sidebarwebhook.action');

Route::post('/sidebarwebhook/ajax', [
    SidebarWebhookController::class,
    'ajax'
])->name('sidebarwebhook.ajax');
