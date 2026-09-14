<?php

use App\Http\Controllers\PartyFinderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PartyFinderController::class, 'index'])->name('partyfinder.index');
Route::get('/export', [PartyFinderController::class, 'export'])->name('partyfinder.export');
