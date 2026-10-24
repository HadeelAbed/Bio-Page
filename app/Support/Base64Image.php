<?php

namespace App\Support;

/**
 * Decodes a base64 data-URL image submitted as a urlencoded form field.
 *
 * WHY: the production host's edge proxy rejects every multipart/form-data
 * POST with a raw 403, so the dashboard cannot use a normal file upload.
 * A base64 payload inside a urlencoded body passes through untouched.
 */
final class Base64Image
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> mime => extension */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{bytes: string, mime: string, ext: string}|null
     */
    public static function decode(?string $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Accept both "data:image/png;base64,...." and a bare base64 string.
        $declaredMime = null;

        if (preg_match('/^data:(image\/(?:jpeg|png|webp));base64,(.*)$/s', $value, $matches)) {
            $declaredMime = $matches[1];
            $value = $matches[2];
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $value), true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';

        if (! isset(self::EXTENSIONS[$mime])) {
            return null;
        }

        if ($declaredMime !== null && $declaredMime !== $mime) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'ext' => self::EXTENSIONS[$mime],
        ];
    }
}
