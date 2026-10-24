{{--
  Password reset — request form.
  Server-side flow: PasswordResetLinkController::store().
--}}
<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>نسيت كلمة المرور | Bio Page</title>
    <script src="{{ asset('js/tailwind-3.4.17.js') }}"></script>
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" />
    <style> body { font-family: "Tajawal", sans-serif; } </style>
  </head>
  <body class="min-h-screen bg-gradient-to-b from-[#b88c8f] via-[#c99a9e] to-[#ba8b8f] flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-white/25 backdrop-blur-xl border border-white/40 rounded-3xl p-8 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.3)]">
      <h1 class="text-2xl font-bold text-gray-900 text-center">استعادة كلمة المرور</h1>
      <p class="text-xs text-gray-700 text-center mt-1 mb-6">أدخلي بريدك المسجل وسنرسل لك رابطًا لتعيين كلمة مرور جديدة.</p>

      @if (session('status'))
        <p class="text-xs text-green-900 bg-green-100/70 border border-green-300 rounded-xl p-3 mb-4">
          {{ session('status') }}
        </p>
      @endif

      <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
          <label for="email" class="block text-sm font-semibold text-gray-900 mb-1">البريد الإلكتروني</label>
          <input
            id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
            class="w-full rounded-xl border border-white/50 bg-white/70 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 @error('email') ring-2 ring-red-400 @enderror"
          />
          @error('email') <p class="text-xs text-red-700 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold py-3 rounded-xl transition">
          إرسال رابط الاستعادة
        </button>
      </form>

      <p class="text-xs text-gray-700 text-center mt-6">
        تذكرتِ كلمة المرور؟
        <a href="{{ route('login') }}" class="font-bold text-gray-900 underline">العودة لتسجيل الدخول</a>
      </p>
    </div>
  </body>
</html>
