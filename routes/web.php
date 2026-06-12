<?php

use App\Http\Controllers\StarterController;
use App\Livewire\Configurator;
use Illuminate\Support\Facades\Route;

Route::get('/', Configurator::class)->name('configurator.index');
Route::get('/c/{id}', Configurator::class)->name('configurator.show');

// bougie starter-pack manifest (schema 1), consumed by
// `bougie new --starter <url>`. `/starter.json` is the default starter;
// `/c/{id}/starter.json` exports a saved configuration.
Route::get('/starter.json', [StarterController::class, 'defaultStarter'])->name('starter.default');
Route::get('/c/{id}/starter.json', [StarterController::class, 'show'])->name('starter.show');
