<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactListController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::post('/contacts/{contact}/unsubscribe', [ContactController::class, 'unsubscribe']);

    Route::get('/contact-lists', [ContactListController::class, 'index']);
    Route::post('/contact-lists', [ContactListController::class, 'store']);
    Route::post('/contact-lists/{contactList}/contacts', [ContactListController::class, 'addContact']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::post('/campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch']);
});
