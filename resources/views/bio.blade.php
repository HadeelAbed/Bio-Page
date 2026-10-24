{{-- =============================================================================
   PHASE 5 — VIEW 1/2: bio.blade.php  (PUBLIC bio page)
   -----------------------------------------------------------------------------
   Rendered by BioController::show($username). All content comes from the
   database via $user / $profile / $user->links — the template contains ZERO
   hardcoded copy, so it is the single presentation surface for every account.

   COLOUR SAFETY: every inline style value is echoed through {{ }} (HTML-escaped)
   AND was already restricted to /^#[0-9A-Fa-f]{6}$/ by UpdateProfileRequest,
   so CSS/JS injection through the colour fields is impossible (two independent
   layers: validation regex + output escaping).

   RESPONSIVENESS (mirrors the original prototype):
     • mobile  → full-bleed page, natural scroll
     • ≥768px  → floating 440px "device card" with 42px radius + deep shadow
   ============================================================================== --}}
<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    {{-- Dynamic <title>: display_name ▸ user name ▸ username
         (?-> nullsafe: a brand-new account may have no profile row yet) --}}
    <title>{{ $profile?->display_name ?? $user->name }} | {{ '@' . $user->username }}</title>

    {{-- Tailwind + vendor assets are served locally (no third-party CDN) --}}
    <script src="{{ asset('js/tailwind-3.4.17.js') }}"></script>
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet" />

    {{-- Typography: self-hosted Tajawal + Plus Jakarta Sans --}}
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" />

    {{-- Alpine.js: powers the share/clipboard button on this page --}}
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>

    <style>
      body { font-family: "Tajawal", "Plus Jakarta Sans", sans-serif; }

      /* Gradient-masking: fade the cover image into the profile gradient so
         there is no hard seam between photo and background (the signature
         "editorial" look of the platform). */
      .header-mask {
        mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
        -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
      }

      /* Glassmorphism link card */
      .glass-btn {
        background: rgba(255,255,255,0.28);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.45);
        box-shadow: 0 4px 18px 0 rgba(0,0,0,0.04);
        transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      }

      /* Hover only on pointer devices — touch screens keep the resting state */
      @media (hover: hover) {
        .glass-btn:hover {
          background: rgba(255,255,255,0.48);
          border-color: rgba(255,255,255,0.7);
          transform: translateY(-3px);
          box-shadow: 0 10px 25px -3px rgba(0,0,0,0.08);
        }
      }

      /* Invisible scrollbar keeps the floating-card illusion intact */
      ::-webkit-scrollbar { width: 0px; background: transparent; }
    </style>
  </head>

  {{-- The gradient itself is data-driven: bg_color_1 (top) ▸ bg_color_2 (bottom) --}}
  <body
    class="min-h-screen flex items-center justify-center p-0 md:p-6 lg:p-10 selection:bg-rose-200"
    style="background-color: {{ $profile?->page_background_color ?? '#f4efed' }};"
  >
    {{-- Ambient blur glow, desktop only (decorative; pointer-events:none so it
         never intercepts clicks) --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none hidden md:block">
      <div class="absolute -top-32 -left-32 w-96 h-96 bg-rose-300/30 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-stone-400/30 rounded-full blur-3xl"></div>
    </div>

    {{-- Main card: full-bleed on mobile, floating rounded card on ≥md.
         Profile colors are validated as hex before reaching inline styles. --}}
    <main
      style="background: linear-gradient(to bottom, {{ $profile?->bg_color_1 ?? '#b88c8f' }}, {{ $profile?->bg_color_2 ?? '#ba8b8f' }}); color: {{ $profile?->text_color ?? '#1a1a1a' }};"
      class="w-full md:max-w-[440px] min-h-screen md:min-h-[820px] md:max-h-[90vh] overflow-y-auto relative flex flex-col items-center pb-12 md:rounded-[42px] md:shadow-[0_25px_60px_-15px_rgba(0,0,0,0.35)] md:border md:border-white/20"
    >
      {{-- Top action bar. Share uses the Web Share API on mobile with a
           clipboard-copy fallback for desktop browsers. --}}
      <header class="w-full absolute top-0 left-0 p-5 flex justify-between items-center z-20 text-white">
        <a
          href="{{ route('login') }}"
          class="w-10 h-10 rounded-full bg-black/15 hover:bg-black/25 backdrop-blur-md border border-white/30 flex items-center justify-center transition shadow-sm"
          title="لوحة التحكم"
        >
          <i class="fa-solid fa-asterisk text-sm"></i>
        </a>

        <button
          type="button"
          x-data="{
              copied: false,
              async share() {
                  const url = window.location.href;
                  const title = {{ Js::from($profile?->display_name ?? $user->name) }};
                  if (navigator.share) {
                      try { await navigator.share({ title, url }); } catch (e) { /* cancelled */ }
                  } else {
                      await navigator.clipboard.writeText(url);
                      this.copied = true;
                      setTimeout(() => this.copied = false, 1800);
                  }
              }
          }"
          @click="share()"
          class="w-10 h-10 rounded-full bg-black/15 hover:bg-black/25 backdrop-blur-md border border-white/30 flex items-center justify-center transition shadow-sm relative"
          title="مشاركة"
        >
          <i class="fa-solid fa-arrow-up-from-bracket text-sm"></i>
          <span
            x-show="copied"
            x-transition.opacity
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 text-[10px] bg-black/70 text-white px-2 py-1 rounded-md whitespace-nowrap"
          >تم نسخ الرابط ✓</span>
        </button>
      </header>

      {{-- Cover image — OPTIONAL. When the user has not uploaded one (or the
           file was removed), the section is skipped entirely and the profile
           gradient becomes the background on its own. --}}
      @php($coverUrl = $profile?->header_image_url)
      @if ($coverUrl)
        <section class="w-full h-80 relative overflow-hidden flex-shrink-0">
          <img
            src="{{ $coverUrl }}"
            alt="{{ $profile?->display_name ?? $user->name }}"
            class="w-full h-full object-cover object-top header-mask"
          />
        </section>
      @endif

      {{-- Identity block — colour follows the DB-configured text_color.
           Without a cover we add top padding to clear the floating action bar. --}}
      <section
        style="color: {{ $profile?->text_color ?? '#1a1a1a' }};"
        class="w-full px-6 {{ $coverUrl ? 'mt-[-15px]' : 'pt-28' }} z-10 flex flex-col items-center flex-shrink-0 text-center"
      >
        <h1 class="text-3xl font-serif tracking-wide font-normal">
          {{ $profile?->display_name ?? $user->name }}
        </h1>
        <p class="text-xs md:text-sm font-light mt-2 max-w-xs leading-relaxed opacity-90">
          {{ $profile?->bio ?? $user->username }}
        </p>
      </section>


      {{-- ===================================================================
           DYNAMIC LINKS — the heart of the bio page.
           Controller guarantees: only is_active rows, ordered by `order`.
           Each card is a glassmorphism anchor that inherits the profile's
           text colour via the inline style on <main>.
           =================================================================== --}}
      <section class="w-full px-6 mt-7 space-y-3.5 z-10">
        @forelse ($user->links as $link)
          <a
            href="{{ $link->url }}"
            target="_blank"
            rel="noopener noreferrer"   {{-- security: prevents window.opener
                                             access on outbound links --}}
            class="glass-btn flex items-center justify-between p-4 rounded-2xl w-full font-medium group"
          >
            {{-- Spacer keeps the label optically centred against the icon --}}
            <span class="w-5"></span>
            <span class="text-sm tracking-wide text-center flex-1 line-clamp-2">
              {{ $link->title }}
            </span>
            <i
              class="fa-solid fa-arrow-up-right-from-square text-xs px-1 opacity-60 group-hover:opacity-100 transition"
            ></i>
          </a>
        @empty
          {{-- First-run state: the account exists but has not added links yet.
               Showing guidance beats rendering an empty column. --}}
          <div class="glass-btn p-6 rounded-2xl text-center text-sm opacity-80">
            لا توجد روابط منشورة بعد ✨
          </div>
        @endforelse
      </section>

      {{-- Quiet footer crediting the platform --}}
      <footer class="mt-auto pt-8 text-center text-xs font-light opacity-70">
        <span>Powered by Bio Page</span>
      </footer>
    </main>
  </body>
</html>
