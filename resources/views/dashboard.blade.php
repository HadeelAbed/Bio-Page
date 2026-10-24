{{-- =============================================================================
   PHASE 5 — VIEW 2/2: dashboard.blade.php  (PRIVATE editor)
   -----------------------------------------------------------------------------
   Rendered by DashboardController::edit() for the AUTHENTICATED user only.

   ARCHITECTURE — "one source of truth, two surfaces":
   A single Alpine component (x-data on <body>) holds the entire editor state.
   The FORM (left panel) and the PHONE PREVIEW (right panel) are two views of
   that same reactive object — edits re-render the preview in real time with
   ZERO server round-trips, while submission is a plain, progressive-enhancement
   multipart POST (no fetch, no SPA). JavaScript failing ⇒ the form still works,
   only the live preview degrades.

   DATA INJECTION — @js() vs @json():
   • @js() emits JSON.parse('…') with quote/angle-bracket escaping, so it is
     safe INSIDE a double-quoted HTML attribute like x-data="…". Bare @json()
     would emit raw double quotes and break the attribute — which is exactly
     why @js()/Js::from() exists. We use @js() for scalars and $linksState.
   ============================================================================== --}}
<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>محرر الصفحة والروابط | {{ '@' . auth()->user()->username }}</title>

    <script src="{{ asset('js/tailwind-3.4.17.js') }}"></script>
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet" />

    <style>
      body { font-family: "Tajawal", "Plus Jakarta Sans", sans-serif; }
      [x-cloak] { display: none !important; }

      /* Same gradient-masking as the public page — the preview must be
         pixel-faithful or it stops being trustworthy. */
      .header-mask {
        mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 55%, rgba(0,0,0,0) 100%);
        -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 55%, rgba(0,0,0,0) 100%);
      }
      .glass-btn { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); transition: all 0.25s ease; }
      .no-scrollbar::-webkit-scrollbar { display: none; }
      .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
  </head>

  @php
      /**
       * SERVER-SIDE STATE HYDRATION (order of precedence):
       *   1. old('…')       → the user's own input survives a validation bounce
       *   2. $profile/$links → the database truth
       *
       * is_active is normalised to a real boolean here because old() returns
       * the strings "0"/"1" from the hidden inputs — and in JavaScript the
       * string "0" is TRUTHY, which would silently break the toggle after a
       * failed submit. Normalising in PHP kills that whole class of bug.
       */
      $linksState = collect(old('links', $links->map(fn ($l) => [
          'id' => $l->id, 'title' => $l->title, 'url' => $l->url, 'is_active' => $l->is_active,
      ])->toArray()))
          ->map(fn ($l) => [
              'id'        => $l['id'] ?? null,
              'title'     => $l['title'] ?? '',
              'url'       => $l['url'] ?? '',
              'is_active' => (bool) ($l['is_active'] ?? true),
          ])
          ->values();
  @endphp
  <body
    class="bg-gray-100 font-sans text-gray-800"
    x-data="{
        /* ---------------- EDITOR STATE (single source of truth) ------------- */
        name: @js(old('display_name', $profile->display_name ?? '')),
        bio: @js(old('bio', $profile->bio ?? '')),

        /* Cover preview: empty when no cover is uploaded. The editor shows the
           chosen gradient only — no stock placeholder image. */
        headerImg: @js($profile->header_image_url),

        /* Cover payload for the server (data URL). Kept in a hidden field so
           the form submits urlencoded — multipart is blocked by the host WAF. */
        headerImageBase64: '',
        headerImageBusy: false,
        headerError: '',

        /* Set when the user explicitly clears an existing cover. */
        removeHeaderImage: false,

        bgColor1: @js(old('bg_color_1', $profile->bg_color_1)),
        bgColor2: @js(old('bg_color_2', $profile->bg_color_2)),
        textColor: @js(old('text_color', $profile->text_color)),
        pageBackgroundColor: @js(old('page_background_color', $profile->page_background_color)),
        buttonBg: 'rgba(255, 255, 255, 0.28)',
        buttonBorder: 'rgba(255, 255, 255, 0.45)',

        /* Curated palettes (static UX sugar — never submitted to the server) */
        presets: [
            { name: 'Dusty Rose',   c1: '#b88c8f', c2: '#ba8b8f', text: '#111827' },
            { name: 'Mocha Nude',   c1: '#d7c4b7', c2: '#a48b78', text: '#1f1610' },
            { name: 'Olive Sage',   c1: '#94a187', c2: '#68775c', text: '#142010' },
            { name: 'Dark Luxe',    c1: '#1f1f23', c2: '#0e0e11', text: '#f3f4f6' },
            { name: 'Soft Lavender',c1: '#c4b5fd', c2: '#a78bfa', text: '#1e1b4b' }
        ],

        /* Hydrated from the database / old input (see @php block above) */
        links: @js($linksState),

        /* ------------------------- BEHAVIOUR ------------------------------- */
        applyPreset(preset) {
            this.bgColor1 = preset.c1;
            this.bgColor2 = preset.c2;
            this.textColor = preset.text;
        },

        /* Read the chosen file into a data URL. The <input> has no name, so the
           browser never builds a multipart body; the data URL is POSTed as a
           normal urlencoded field and decoded server-side. */
        previewImage(event) {
            const input = event.target;
            const file = input.files && input.files[0];

            if (!file) return;

            this.headerError = '';
            this.removeHeaderImage = false;

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.headerError = 'صيغة الصورة المسموحة: JPG أو PNG أو WEBP فقط.';
                input.value = '';
                this.headerImageBase64 = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                this.headerError = 'حجم الصورة لا يتجاوز 2 ميغابايت.';
                input.value = '';
                this.headerImageBase64 = '';
                return;
            }

            this.headerImageBusy = true;

            const reader = new FileReader();

            reader.onload = () => {
                this.headerImg = reader.result;
                this.headerImageBase64 = reader.result;
                this.headerImageBusy = false;
            };

            reader.onerror = () => {
                this.headerImageBusy = false;
                this.headerError = 'تعذر قراءة الصورة، حاولي مرة أخرى.';
                this.headerImageBase64 = '';
            };

            reader.readAsDataURL(file);
        },

        /* Optional cover: clear the current image and flag it for deletion
           on the next save. The file input is reset so it cannot silently
           re-upload the old selection. */
        removeHeader() {
            this.headerImg = null;
            this.headerImageBase64 = '';
            this.headerError = '';
            this.removeHeaderImage = true;
            if (this.$refs.headerImageInput) this.$refs.headerImageInput.value = '';
        },

        addLink() {
            if (this.links.length >= 25) return;
            this.links.push({ id: null, title: 'رابط جديد ' + (this.links.length + 1), url: 'https://', is_active: true });
        },

        moveLink(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.links.length) return;
            [this.links[index], this.links[target]] = [this.links[target], this.links[index]];
        },

        removeLink(index) { this.links.splice(index, 1); },

        toggleLink(index) { this.links[index].is_active = !this.links[index].is_active; }
    }"
  >

    {{-- Flash toast: appears after a successful save, auto-dismisses via
         a scoped Alpine instance (independent from the editor state). --}}
    @if (session('status'))
      <div
        x-data="{ open: true }"
        x-init="setTimeout(() => open = false, 3500)"
        x-show="open"
        x-transition.opacity
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-gray-900 text-white text-sm px-5 py-3 rounded-xl shadow-xl"
      >
        {{ session('status') }}
      </div>
    @endif

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
      @csrf
    </form>

    <div class="min-h-screen flex flex-col lg:flex-row">
      {{-- ================= RIGHT PANEL: controls + real <form> ============= --}}
      <aside class="w-full lg:w-1/2 p-6 lg:p-10 bg-white border-l border-gray-200 overflow-y-auto max-h-screen">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
          <span class="text-sm font-semibold text-gray-500">لوحة التحكم</span>
          <button
            type="submit"
            form="logout-form"
            class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 hover:bg-red-50 px-3 py-2 rounded-xl transition"
          >
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <span>تسجيل الخروج</span>
          </button>
        </div>

        {{-- REAL form: standard multipart POST (no JS required to submit).
             @csrf  → session-bound CSRF token (Laravel rejects tokenless POSTs)
             @method('PUT') → HTML forms cannot PUT; Laravel's method spoofing
                              middleware translates the hidden field back. --}}
        <form
          method="POST"
          action="{{ route('dashboard.update') }}"
          class="mb-8 pb-4 border-b border-gray-100"
        >
          @csrf
          @method('PUT')

          <div class="flex items-center justify-between mb-2 gap-3 flex-wrap">
            <div>
              <h2 class="text-2xl font-bold text-gray-900">تخصيص الهوية والروابط</h2>
              <p class="text-xs text-gray-500 mt-1">
                ارفعي صورتك، اختاري ألوانك المفضلة، وأضيفي روابطك بحرية
              </p>
            </div>

            {{-- The prototype's decorative button is now the ACTUAL submit.
                 type=submit ⇒ works without JavaScript too. --}}
            <div class="flex items-center gap-2">
              {{-- Live link to the public page, shown once the profile exists --}}
              <a
                href="{{ route('bio.show', auth()->user()->username) }}"
                target="_blank"
                class="text-xs text-gray-500 hover:text-gray-900 transition flex items-center gap-1"
                title="فتح صفحتك العامة"
              >
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span dir="ltr">{{ '@' . auth()->user()->username }}</span>
              </a>
              <button
                type="submit"
                :disabled="headerImageBusy"
                :class="headerImageBusy ? 'opacity-60 cursor-wait' : ''"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow-sm transition"
              >
                <span x-show="!headerImageBusy">حفظ ونشر الرابط</span>
                <span x-show="headerImageBusy" x-cloak>جارٍ تجهيز الصورة…</span>
              </button>
            </div>
          </div>

          {{-- VALIDATION ERROR SUMMARY — FormRequest failures land in $errors.
               Listing every message once beats hunting field-by-field. --}}
          @if ($errors->any())
            <div class="mt-3 mb-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl p-4 space-y-1">
              <p class="font-bold mb-1">تعذر الحفظ — راجعي النقاط التالية:</p>
              @foreach ($errors->all() as $error)
                <p>• {{ $error }}</p>
              @endforeach
            </div>
          @endif

          {{-- ============ 1. HEADER IMAGE UPLOAD (OPTIONAL) ============== --}}
          <div class="mb-8">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">1. صورة الهيدر الشخصية (اختياري)</h3>
            <div class="flex items-center gap-4">
              {{-- Thumbnail reflects the live Alpine state. When no image is
                   chosen we show a neutral placeholder instead of a stock photo. --}}
              <div class="w-20 h-20 rounded-2xl overflow-hidden border border-gray-200 shadow-sm flex-shrink-0 bg-gray-50 flex items-center justify-center">
                <template x-if="headerImg">
                  <img :src="headerImg" alt="معاينة الصورة" class="w-full h-full object-cover" />
                </template>
                <i x-show="!headerImg" class="fa-regular fa-image text-2xl text-gray-300" aria-hidden="true"></i>
              </div>
              <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <label class="cursor-pointer inline-flex items-center gap-2 bg-gray-50 hover:bg-gray-100 border border-gray-300 text-gray-700 text-xs font-semibold px-4 py-2.5 rounded-xl transition">
                    <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                    <span>اختيار صورة من جهازك</span>
                    {{-- No name= on purpose: the file must NOT be submitted as
                         multipart (the production host's WAF answers multipart
                         POSTs with 403). JS reads it and fills the hidden
                         base64 field below; the form stays urlencoded. --}}
                    <input
                      type="file"
                      x-ref="headerImageInput"
                      @change="previewImage($event)"
                      accept="image/jpeg,image/png,image/webp"
                      class="hidden"
                    />
                  </label>
                  <button
                    type="button"
                    x-show="headerImg"
                    @click="removeHeader()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 border border-transparent px-3 py-2.5 rounded-xl transition"
                  >
                    <i class="fa-regular fa-trash-can text-sm"></i>
                    <span>إزالة الصورة</span>
                  </button>
                </div>
                {{-- Deletion flag consumed by DashboardController::update() --}}
                <input type="hidden" name="remove_header_image" :value="removeHeaderImage ? 1 : 0" />
                {{-- The cover travels as a data URL inside this urlencoded field. --}}
                <input type="hidden" name="header_image_base64" x-model="headerImageBase64" />
                <p x-show="headerError" x-cloak x-text="headerError" class="text-[11px] text-red-600 mt-1.5"></p>
                <p class="text-[11px] text-gray-400 mt-1.5">اختياري — اتركيه فارغًا إن لم ترغبي بإضافة صورة. JPG / PNG / WEBP بحد أقصى 2MB.</p>
              </div>
            </div>
          </div>

          {{-- ==================== 2. COLOURS & GRADIENT ===================== --}}
          <div class="mb-8 border-t border-gray-100 pt-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">2. الألوان وتدرج الخلفية</h3>

            {{-- Preset palettes — pure client-side sugar; they only write into
                 the three reactive colour fields below, which are the values
                 actually submitted. --}}
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 mb-2">تدرجات جاهزة مقترحة:</label>
              <div class="flex flex-wrap gap-2">
                <template x-for="p in presets" :key="p.name">
                  <button
                    @click="applyPreset(p)"
                    type="button"
                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 hover:border-gray-400 flex items-center gap-2 transition bg-white shadow-xs"
                  >
                    <span
                      class="w-3.5 h-3.5 rounded-full border border-black/10 inline-block"
                      :style="`background: linear-gradient(to right, ${p.c1}, ${p.c2})`"
                    ></span>
                    <span x-text="p.name"></span>
                  </button>
                </template>
              </div>
            </div>

            {{-- Hex fields are submitted directly; colour pickers stay synced
                 with them for users who prefer choosing visually. --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
              <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">تدرج أعلى الخلفية</label>
                <div class="flex items-center gap-2">
                  <input type="color" x-model="bgColor1" aria-label="اختيار لون التدرج الأعلى" class="w-10 h-9 rounded-lg cursor-pointer border border-gray-200 bg-white p-0.5" />
                  <input type="text" name="bg_color_1" x-model="bgColor1" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" placeholder="#b88c8f" dir="ltr" aria-label="كود لون التدرج الأعلى" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-mono text-gray-700 focus:outline-none focus:ring-2 focus:ring-rose-300" />
                </div>
              </div>

              <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">تدرج أسفل الخلفية</label>
                <div class="flex items-center gap-2">
                  <input type="color" x-model="bgColor2" aria-label="اختيار لون التدرج الأسفل" class="w-10 h-9 rounded-lg cursor-pointer border border-gray-200 bg-white p-0.5" />
                  <input type="text" name="bg_color_2" x-model="bgColor2" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" placeholder="#ba8b8f" dir="ltr" aria-label="كود لون التدرج الأسفل" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-mono text-gray-700 focus:outline-none focus:ring-2 focus:ring-rose-300" />
                </div>
              </div>

              <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">لون النصوص والأسماء</label>
                <div class="flex items-center gap-2">
                  <input type="color" x-model="textColor" aria-label="اختيار لون النصوص والأسماء" class="w-10 h-9 rounded-lg cursor-pointer border border-gray-200 bg-white p-0.5" />
                  <input type="text" name="text_color" x-model="textColor" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" placeholder="#1a1a1a" dir="ltr" aria-label="كود لون النصوص والأسماء" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-mono text-gray-700 focus:outline-none focus:ring-2 focus:ring-rose-300" />
                </div>
              </div>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-rose-50/60 p-4">
              <label for="page-background-color" class="block text-xs font-semibold text-gray-700 mb-2">لون خلفية الصفحة خلف الجوال</label>
              <div class="flex items-center gap-2">
                <input type="color" x-model="pageBackgroundColor" aria-label="اختيار لون خلفية الصفحة خلف الجوال" class="h-10 w-12 rounded-lg cursor-pointer border border-gray-200 bg-white p-0.5" />
                <input type="text" id="page-background-color" name="page_background_color" x-model="pageBackgroundColor" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" placeholder="#f4efed" dir="ltr" class="w-32 rounded-lg border border-gray-200 bg-white px-3 py-2 text-left text-sm font-mono text-gray-700 focus:outline-none focus:ring-2 focus:ring-rose-300" />
              </div>
              <p class="mt-2 text-[11px] text-gray-500">هذا اللون يظهر حول صفحة الروابط المنشورة، وليس داخل شاشة الجوال.</p>
            </div>
          </div>

          {{-- ==================== 3. NAME & BIO ============================= --}}
          <div class="space-y-4 mb-8 border-t border-gray-100 pt-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">3. الاسم والنبذة التعريفية</h3>

            <div>
              <label class="block text-sm font-semibold mb-1" for="display_name">الاسم الأساسي:</label>
              <input
                type="text"
                id="display_name"
                name="display_name"
                x-model="name"
                maxlength="60"
                class="w-full border rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 @error('display_name') border-red-400 @else border-gray-300 @enderror"
              />
            </div>

            <div>
              <label class="block text-sm font-semibold mb-1" for="bio">النبذة (Bio):</label>
              <textarea
                id="bio"
                name="bio"
                x-model="bio"
                rows="2"
                maxlength="160"
                class="w-full border rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 @error('bio') border-red-400 @else border-gray-300 @enderror"
              ></textarea>
              {{-- Live counter: the 160-char cap is also enforced by the
                   FormRequest; showing it client-side prevents the bounce. --}}
              <p class="text-[11px] text-gray-400 mt-1 text-left" dir="ltr">
                <span x-text="bio.length"></span>/160
              </p>
            </div>
          </div>

          {{-- ==================== 4. DYNAMIC LINKS ========================== --}}
          <div class="border-t border-gray-100 pt-6">
            <div class="flex justify-between items-center mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-900">4. الروابط والأزرار المخصصة</h3>
                <p class="text-xs text-gray-500">أضيفي أي روابط ترغبين بها مع التسمية التي تفضلينها</p>
              </div>

              <button
                @click="addLink()"
                type="button"
                :disabled="links.length >= 25"
                :title="links.length >= 25 ? 'وصلتِ إلى الحد الأقصى وهو 25 رابطاً' : 'إضافة رابط جديد'"
                class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition flex items-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>إضافة رابط</span>
              </button>
            </div>

            <div class="space-y-3">
              {{-- Alpine renders one row per link. Every row emits FOUR named
                   inputs whose names are computed from the INDEX, producing the
                   nested payload the FormRequest expects:
                     links[0][id] / links[0][title] / links[0][url] / links[0][is_active]

                   ★ The hidden [id] input is the linchpin of the sync logic:
                   rows loaded from the DB carry their id (→ UPDATE path), rows
                   created client-side send an empty id (→ INSERT path), and
                   rows omitted from the DOM entirely are deleted server-side.
                   ★ Security: even a FORGED id cannot touch another user's
                   link — DashboardController scopes every lookup through
                   $user->links()->whereKey(). --}}
              <template x-for="(link, index) in links" :key="link.id ?? ('new-' + index)">
                <div
                  data-link-row
                  class="p-3.5 bg-gray-50 hover:bg-gray-100/70 transition rounded-2xl border border-gray-200 flex items-center gap-3"
                  :class="{ 'opacity-50' : !link.is_active }"
                >
                  <input type="hidden" :name="`links[${index}][id]`" :value="link.id ?? ''" />
                  <input type="hidden" :name="`links[${index}][is_active]`" :value="link.is_active ? '1' : '0'" />

                  <div class="flex flex-col -my-1">
                    <button
                      @click="moveLink(index, -1)"
                      type="button"
                      :aria-label="`تحريك ${link.title} لأعلى`"
                      title="تحريك الرابط لأعلى"
                      :disabled="index === 0"
                      class="w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      <i class="fa-solid fa-chevron-up text-[10px]" aria-hidden="true"></i>
                    </button>
                    <button
                      @click="moveLink(index, 1)"
                      type="button"
                      :aria-label="`تحريك ${link.title} لأسفل`"
                      title="تحريك الرابط لأسفل"
                      :disabled="index === links.length - 1"
                      class="w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </button>
                  </div>

                  <div class="flex-1 space-y-2">
                    <div>
                      <label class="block text-[11px] font-medium text-gray-500 mb-0.5">اسم الزر:</label>
                      <input
                        type="text"
                        x-model="link.title"
                        :name="`links[${index}][title]`"
                        maxlength="80"
                        placeholder="مثال: حسابي على تويتر، متجري، تيك توك..."
                        class="w-full text-xs font-semibold border border-gray-200 rounded-lg p-2.5 bg-white focus:outline-none focus:ring-1 focus:ring-rose-400"
                      />
                    </div>
                    <div>
                      <label class="block text-[11px] font-medium text-gray-500 mb-0.5">الرابط المباشر:</label>
                      <input
                        type="url"
                        x-model="link.url"
                        :name="`links[${index}][url]`"
                        placeholder="https://..."
                        dir="ltr"
                        class="w-full text-xs text-gray-600 border border-gray-200 rounded-lg p-2.5 bg-white focus:outline-none focus:ring-1 focus:ring-rose-400 text-left"
                      />
                    </div>
                  </div>

                  {{-- Publish switch: flips the hidden [is_active] input above.
                       Inactive links are saved but filtered out of the public
                       page at QUERY level (BioController). --}}
                  <button
                    @click="toggleLink(index)"
                    type="button"
                    :title="link.is_active ? 'إخفاء من الصفحة العامة' : 'إظهار في الصفحة العامة'"
                    class="w-9 h-9 flex items-center justify-center rounded-xl transition"
                    :class="link.is_active ? 'text-emerald-600 hover:bg-emerald-50' : 'text-gray-400 hover:bg-gray-200'"
                  >
                    <i class="fa-solid" :class="link.is_active ? 'fa-eye' : 'fa-eye-slash'"></i>
                  </button>

                  <button
                    @click="removeLink(index)"
                    type="button"
                    title="حذف"
                    class="w-9 h-9 flex items-center justify-center rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 transition"
                  >
                    <i class="fa-regular fa-trash-can"></i>
                  </button>
                </div>
              </template>

              {{-- Empty state nudges the first add --}}
              <p x-show="links.length === 0" class="text-center text-xs text-gray-400 py-6">
                لا توجد روابط بعد — اضغطي «إضافة رابط» للبدء.
              </p>
            </div>
          </div>
        </form>
      </aside>

      {{-- ================= LEFT PANEL: LIVE PHONE PREVIEW ==================
           Reads directly from the SAME Alpine state as the form — every
           keystroke repaints the mock instantly. This markup intentionally
           mirrors bio.blade.php's structure/classes so the preview is a
           faithful WYSIWYG of what visitors will see. --}}
      <main class="w-full lg:w-1/2 flex items-center justify-center p-6 lg:p-10 sticky top-0 h-screen" :style="`background-color: ${pageBackgroundColor}`">
        {{-- Phone mockup --}}
        <div class="w-[340px] h-[670px] bg-black rounded-[48px] p-3 shadow-2xl ring-1 ring-gray-900/15 relative flex flex-col">
          {{-- Dynamic Island flourish --}}
          <div class="absolute top-4 left-1/2 -translate-x-1/2 w-24 h-4 bg-black rounded-full z-30"></div>

          {{-- Screen: gradient bound live to the two colour states --}}
          <div
            class="w-full h-full rounded-[40px] overflow-hidden flex flex-col relative no-scrollbar overflow-y-auto"
            :style="`background: linear-gradient(to bottom, ${bgColor1}, ${bgColor2})`"
          >
            {{-- Cover image — optional, exactly like production. --}}
            <template x-if="headerImg">
              <div class="w-full h-64 flex-shrink-0 relative overflow-hidden">
                <img :src="headerImg" alt="معاينة الغلاف" class="w-full h-full object-cover object-top header-mask" />
              </div>
            </template>

            {{-- Identity block (name + bio follow the live text state) --}}
            <div
              class="px-5 text-center z-10 flex flex-col items-center flex-shrink-0"
              :class="headerImg ? 'mt-[-15px]' : 'pt-24'"
              :style="`color: ${textColor}`"
            >
              <h4 class="text-xl font-serif font-normal" x-text="name || 'اسمك هنا'"></h4>
              <p class="text-[11px] opacity-85 mt-1.5 leading-relaxed line-clamp-2" x-text="bio"></p>
            </div>

            {{-- Glass link cards mirror the real .glass-btn styling --}}
            <div class="px-4 mt-5 space-y-2.5 flex-1 pb-8">
              <template x-for="(link, idx) in links.filter(l => l.is_active)" :key="link.id ?? ('new-' + idx)">
                <div
                  class="glass-btn flex items-center justify-between p-3.5 rounded-2xl shadow-xs"
                  :style="`background: ${buttonBg}; border: 1px solid ${buttonBorder}; color: ${textColor}`"
                >
                  <span class="w-3"></span>
                  <span class="text-xs font-medium text-center truncate max-w-[200px]" x-text="link.title || 'بدون عنوان'"></span>
                  <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-60 px-1"></i>
                </div>
              </template>

              {{-- Preview empty state --}}
              <p
                x-show="links.filter(l => l.is_active).length === 0"
                class="text-[11px] text-center opacity-60 py-4"
              >ستظهر روابطك المنشورة هنا</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>
