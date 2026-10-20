<?php

/**
 * ---------------------------------------------------------------------------
 * PHASE 4 — Route definitions
 * ---------------------------------------------------------------------------
 * FILE ORDER IS SEMANTIC. Laravel matches routes TOP-DOWN, so the public
 * wildcard /{username} MUST be declared LAST: any single-segment GET route
 * registered after it would be shadowed (e.g. the wildcard would swallow
 * /settings before a settings controller ever saw it).
 *
 * RESERVED SLUG PROTECTION. Even with correct ordering, the wildcard would
 * still answer GET /logout, /login… (routes that only exist as POST). A
 * visitor — or a crawler — hitting those URLs would receive a bio page for
 * username "logout". The negative lookahead in ->where() kills that entire
 * class of collision AT THE ROUTER LEVEL, so the protected names stay
 * reserved even if someone later deletes the corresponding POST routes.
 * ---------------------------------------------------------------------------
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BioController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/* ===========================================================================
 | 0. LANDING — send visitors to the auth entry point.
 |    (A marketing homepage can replace this later without touching the
 |    wildcard below, because it is a two-segment-safe exact path.)
 * ======================================================================== */
Route::get('/', fn () => view('welcome'))->name('home');

/* ===========================================================================
 | 1. GUEST-ONLY AUTH (/login, /register)
 |    The `guest` middleware bounces already-authenticated users to the
 |    dashboard, preventing "logged-in people seeing a login form".
 * ======================================================================== */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'createRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegister'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});

/* ===========================================================================
 | 2. AUTHENTICATED AREA (/dashboard, /logout)
 |    The `auth` middleware returns a 302 → /login for guests. Every controller
 |    in this group derives its user from the SESSION (zero-trust rule),
 |    never from the request payload.
 * ======================================================================== */
Route::middleware('auth')->group(function () {
    // GET  → render the editor (form + Alpine live preview)
    // PUT  → persist profile/links (method spoofing via @method('PUT'))
    Route::get('/dashboard', [DashboardController::class, 'edit'])->name('dashboard.edit');
    Route::put('/dashboard', [DashboardController::class, 'update'])->name('dashboard.update');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});

/* ===========================================================================
 | 3. PUBLIC DYNAMIC BIO PAGE — keep at the BOTTOM of the file.
 |    Regex breakdown of the constraint:
 |      (?!(?:dashboard|login|register|logout)$) excludes exact reserved
 |                                                slugs without blocking valid
 |                                                usernames like dashboard2
 |      [a-zA-Z0-9_-]+                            then URL-safe characters
 |      $                                      entire segment, nothing after
 * ======================================================================== */
Route::get('/{username}', [BioController::class, 'show'])
    ->where('username', '^(?!(?:dashboard|login|register|logout)$)[a-zA-Z0-9_-]+$')
    ->name('bio.show');
