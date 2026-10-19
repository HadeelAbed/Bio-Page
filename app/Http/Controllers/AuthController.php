<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ---------------------------------------------------------------------------
 * PHASE 3 (support) — Minimal session-based auth controller
 * ---------------------------------------------------------------------------
 * WHY hand-rolled instead of Breeze/Jetstream?
 * The project brief mandates PURE Blade + Alpine with no SPA layer and no
 * extra scaffolding packages. This controller is ~90 lines and covers exactly
 * the four flows the platform needs: login, register, logout. Every query and
 * every redirect is visible — nothing is hidden behind generated code.
 *
 * The routes built from this controller (/login, /register, /logout) are
 * precisely the "reserved slugs" that Phase 4's negative-lookahead regex
 * protects from being swallowed by the public /{username} wildcard.
 * ---------------------------------------------------------------------------
 */
class AuthController extends Controller
{
    /* ============================ LOGIN =================================== */

    /**
     * Show the login form (guests only — `auth` middleware redirects
     * authenticated users straight to the dashboard).
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate a user against the session guard.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        // attempt() hashes-compares via the model's 'hashed' cast and issues
        // a session + remember cookie on success.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Uniform error: never disclose WHETHER the e-mail exists.
            return back()->withErrors([
                'email' => 'بيانات الدخول غير صحيحة. جربي مرة أخرى.',
            ])->onlyInput('email');
        }

        // SESSION FIXATION defence: rotate the session id after privilege
        // change, then pull a fresh CSRF token for the next form.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.edit'));
    }

    /* =========================== REGISTER ================================= */

    /**
     * Show the registration form.
     */
    public function createRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Create a brand-new account + its (empty) profile pointer.
     */
    public function storeRegister(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:60'],
            'username' => [
                'required', 'string', 'min:3', 'max:30',
                // SAME charset contract as the Phase 4 route regex — mirroring
                // it here means the validation layer REJECTS anything the
                // router could not serve back (defence in depth, one regex
                // duplicated in exactly two documented places).
                'regex:/^[a-zA-Z0-9_\-]+$/',
                // RESERVED SLUG GUARD: these usernames are router-level system
                // paths (/dashboard, /login, /register, /logout). They pass
                // the charset regex but must never become accounts, otherwise
                // GET /login would be ambiguous between the auth page and a
                // bio page for a user named "login". The lookahead in the
                // Phase 4 route constraint blocks them at the router; this
                // Rule::notIn() blocks them at REGISTRATION, closing the loop.
                Rule::notIn(['dashboard', 'login', 'register', 'logout']),
                // Case-insensitive uniqueness is ALSO guaranteed by the DB
                // index (utf8mb4_unicode_ci); this check just short-circuits
                // with a friendly message before we hit the database.
                'unique:users,username',
            ],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'username.regex'  => 'اسم المستخدم يقبل فقط الحروف والأرقام والشرطة والشرط السفلي.',
            'username.unique' => 'اسم المستخدم محجوز بالفعل، جربي اسمًا آخر.',
            'username.min'    => 'اسم المستخدم 3 أحرف على الأقل.',
            'email.unique'    => 'هذا البريد مسجل مسبقًا.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ]);

        // create() passes through the User model mutator, which lowercases the
        // username a second time (normalisation lives in the MODEL, not here).
        $user = User::create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // The profile row is created lazily by DashboardController::edit()
        // (->make()) and persisted on first save — no empty INSERT needed.
        return redirect()->route('dashboard.edit');
    }

    /* ============================ LOGOUT ================================== */

    /**
     * Terminate the session safely.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();                        // purge auth from the session

        $request->session()->invalidate();     // destroy ALL session data
        $request->session()->regenerateToken(); // rotate CSRF token

        return redirect()->route('login');
    }
}
