<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the "forgot password" form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a freshly generated reset link.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'طلبت رابطًا مؤخرًا. انتظري دقيقة ثم حاولي مجددًا.']);
        }

        // Deliberately identical response for known and unknown addresses so
        // the endpoint cannot be used to enumerate registered accounts.
        return back()->with(
            'status',
            'إذا كان البريد مسجلًا لدينا فسيصلك رابط إعادة تعيين كلمة المرور خلال دقائق. تحققي من صندوق الوارد والمهمل (Spam).'
        );
    }
}
