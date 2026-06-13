<?php

use App\Http\Controllers\ConfiguratorController;
use App\Http\Controllers\StarterController;
use Illuminate\Support\Facades\Route;

// The configurator is a plain Blade page driven entirely by client-side JS
// (resources/js/build-canvas.js + maker-engine.js). Interaction is instant; the
// server is hit only to (re)generate composer.json + the install tree (POST
// /api/build) and to persist a shareable configuration (POST /save).
Route::get('/', [ConfiguratorController::class, 'index'])->name('configurator.index');
Route::get('/c/{id}', [ConfiguratorController::class, 'show'])->name('configurator.show');
Route::post('/api/build', [ConfiguratorController::class, 'build'])->name('configurator.build');
Route::post('/save', [ConfiguratorController::class, 'save'])->name('configurator.save');

// bougie starter-pack manifest (schema 1), consumed by
// `bougie new --starter <url>`. `/starter.json` is the default starter;
// `/c/{id}/starter.json` exports a saved configuration.
Route::get('/starter.json', [StarterController::class, 'defaultStarter'])->name('starter.default');
Route::get('/c/{id}/starter.json', [StarterController::class, 'show'])->name('starter.show');
