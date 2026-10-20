<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ---------------------------------------------------------------------------
 * PHASE 3 — Form Request: profile + links update
 * ---------------------------------------------------------------------------
 * WHY a FormRequest instead of inline $request->validate()?
 *  - Validation rules live in ONE auditable place per use-case.
 *  - Failed validation redirects back with $errors automatically — the
 *    dashboard can render field-level messages without controller glue.
 *  - The rules travel with the request into tests (Rules objects are
 *    inspectable), which inline arrays are not.
 *
 * ZERO-TRUST NOTE: notice there is NO rule for `user_id` and no rule that
 * would ever ACCEPT an id from the client to act upon blindly. The only ids
 * we accept are `links.*.id` — and even those are re-verified against
 * auth()->user()->links in the controller before any UPDATE is issued.
 * ---------------------------------------------------------------------------
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Who may run this request? Anyone authenticated.
     *
     * The `auth` middleware already guarantees a session user before this
     * method is reached; returning true here documents that authorization is
     * middleware-driven. If we later add per-user plans (e.g. max 5 links on
     * free tier), the quota check goes in rules() as a closure rule.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ---- Identity & text -------------------------------------------------
            // Nullable (not required): clearing a field must be allowed, otherwise
            // users could never empty their bio after filling it once.
            'display_name' => ['nullable', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:160'],

            // ---- Header image ----------------------------------------------------
            // `image` + `mimes` double-checks the REAL file signature (not just the
            // extension), which blocks .php files renamed to .jpg — the oldest
            // upload exploit in the book. 2 MB cap protects disk & bandwidth.
            // `dimensions` is deliberately omitted to allow any aspect ratio; the
            // CSS (object-cover + object-top) handles cropping responsively.
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Explicit "remove the existing cover" flag. The cover is optional,
            // so clearing it must be possible without uploading a replacement.
            // Ignored when a new file is supplied (replace wins over remove).
            'remove_header_image' => ['sometimes', 'boolean'],

            // ---- Colours ---------------------------------------------------------
            // Strict 6-digit hex ONLY. The value is interpolated into an inline
            // style="background: linear-gradient(..., VALUE)" in bio.blade.php,
            // so anything not matching this regex is an injection vector for
            // CSS/JS smuggling (e.g. `red; } </style><script>...`).
            'bg_color_1' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bg_color_2' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'page_background_color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // ---- Dynamic links array ---------------------------------------------
            // Alpine submits links as links[0][title], links[1][url], …
            'links' => ['array', 'max:25'],               // sane upper bound
            'links.*.id' => ['nullable', 'integer'],           // existence + OWNERSHIP
            // verified in the
            // controller (a forged
            // id of another user
            // must be ignored, not
            // trusted)
            'links.*.title' => ['required', 'string', 'max:80'],
            'links.*.url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'links.*.is_active' => ['sometimes', 'boolean'],    // 0/1 from the hidden input
        ];
    }

    /**
     * Custom, human-readable (Arabic-first) validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bg_color_1.regex' => 'لون التدرج العلوي غير صالح (الصيغة المطلوبة: #AABBCC).',
            'bg_color_2.regex' => 'لون التدرج السفلي غير صالح (الصيغة المطلوبة: #AABBCC).',
            'text_color.regex' => 'لون النص غير صالح (الصيغة المطلوبة: #AABBCC).',
            'page_background_color.regex' => 'لون خلفية الصفحة غير صالح (الصيغة المطلوبة: #AABBCC).',
            'header_image.image' => 'الملف المرفوع يجب أن يكون صورة.',
            'header_image.mimes' => 'صيغة الصورة المسموحة: JPG, PNG أو WEBP فقط.',
            'header_image.max' => 'حجم الصورة لا يتجاوز 2 ميغابايت.',
            'links.*.title.required' => 'اسم الزر مطلوب لكل رابط.',
            'links.*.url.required' => 'الرابط مطلوب لكل رابط.',
            'links.*.url.url' => 'يجب أن يبدأ الرابط بـ http:// أو https://',
        ];
    }
}
