{{--
  Password reset — new password form (link carries token + email).
  Server-side flow: NewPasswordController::store().
--}}
<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تعيين كلمة مرور جديدة | Bio Page</title>
    <script src="{{ asset('js/tailwind-3.4.17.js') }}"></script>
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" />
    <style> body { font-family: "Tajawal", sans-serif; } </style>
  </head>
  <body class="min-h-screen bg-gradient-to-b from-[#b88c8f] via-[#c99a9e] to-[#ba8b8f] flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-white/25 backdrop-blur-xl border border-white/40 rounded-3xl p-8 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.3)]">
      <h1 class="text-2xl font-bold text-gray-900 text-center">تعيين كلمة مرور جديدة</h1>
      <p class="text-xs text-gray-700 text-center mt-1 mb-6">اختاري كلمة مرور قوية لا تقل عن 8 أحرف.</p>

      <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />

        <div>
          <label for="email" class="block text-sm font-semibold text-gray-900 mb-1">البريد الإلكتروني</label>
          <input
            id="email" type="email" name="email" value="{{ old('email', $email) }}" required
            class="w-full rounded-xl border border-white/50 bg-white/70 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 @error('email') ring-2 ring-red-400 @enderror"
          />
          @error('email') <p class="text-xs text-red-700 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label for="password" class="block text-sm font-semibold text-gray-900 mb-1">كلمة المرور الجديدة</label>
          <input
            id="password" type="password" name="password" required autofocus
            class="w-full rounded-xl border border-white/50 bg-white/70 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 @error('password') ring-2 ring-red-400 @enderror"
          />
          @error('password') <p class="text-xs text-red-700 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label for="password_confirmation" class="block text-sm font-semibold text-gray-900 mb-1">تأكيد كلمة المرور</label>
          <input
            id="password_confirmation" type="password" name="password_confirmation" required
            class="w-full rounded-xl border border-white/50 bg-white/70 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold py-3 rounded-xl transition">
          حفظ كلمة المرور
        </button>
      </form>

      <p class="text-xs text-gray-700 text-center mt-6">
        <a href="{{ route('password.request') }}" class="font-bold text-gray-900 underline">طلب رابط جديد</a>
      </p>
    </div>
  </body>
</html>
