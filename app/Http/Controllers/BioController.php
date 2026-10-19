<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ---------------------------------------------------------------------------
 * PHASE 3 — BioController (PUBLIC, read-only)
 * ---------------------------------------------------------------------------
 * Renders the public bio page at /{username}.
 *
 * SECURITY POSTURE:
 *  - Read-only: this controller never mutates state, so it is safe to hit
 *    with any HTTP method combination and needs no CSRF surface.
 *  - firstOrFail() ⇒ Laravel converts "no such user" into a real 404 instead
 *    of leaking a 500 or an empty page (which would disclose nothing but
 *    still degrades UX).
 *  - Only ACTIVE links are eager-loaded; inactive drafts are filtered at the
 *    QUERY level, so they never even reach the Blade template — no chance of
 *    accidentally rendering a draft via a missing @if.
 * ---------------------------------------------------------------------------
 */
class BioController extends Controller
{
    /**
     * Show the public bio page for the given username.
     *
     * ROUTE MODEL BINDING is intentionally NOT used here: we look up by
     * `username` (a mutable, non-numeric column) instead of by primary key.
     * firstOrFail() gives us the same 404 semantics as ->findOrFail().
     */
    public function show(Request $request, string $username): View
    {
        // N+1 prevention: with() loads profile + links in two extra queries,
        // not one query per rendered link. The query-level orderBy('order')
        // relies on the composite index (user_id, order) from Phase 1.
        $user = User::query()
            ->where('username', $username)
            ->with([
                'profile',
                'links' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('order'),
            ])
            ->firstOrFail();

        return view('bio', [
            'user' => $user,
            // Guard clause for the view: a freshly registered user may not
            // have saved a profile yet. $profile ?? null keeps bio.blade.php
            // able to render sensible defaults instead of crashing on
            // $user->profile->display_name (null->display_name = Error).
            'profile' => $user->profile,
        ]);
    }
}
