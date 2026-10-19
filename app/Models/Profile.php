<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Profile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * SECURITY NOTE: `user_id` is deliberately EXCLUDED. Ownership is granted
     * exclusively through the relationship call sites
     * ($user->profile()->save($profile) / $user->profile()->make()), which set
     * the FK internally. If user_id were fillable, a careless
     * $profile->update($request->validated()) could re-parent a profile.
     *
     * @var list<string>
     */
    protected $fillable = [
        'display_name',
        'bio',
        'header_image',
        'bg_color_1',
        'bg_color_2',
        'text_color',
    ];

    /**
     * Default attribute values applied to UNSAVED instances.
     *
     * The dashboard's edit() method may encounter a user who has never saved a
     * profile. It creates an in-memory instance ($user->profile()->make())
     * which must still render the same palette as the DB-level defaults from
     * the migration — otherwise the live preview would show an empty gradient.
     * Mirroring the migration defaults here keeps both layers in agreement.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'bg_color_1' => '#b88c8f',
        'bg_color_2' => '#ba8b8f',
        'text_color' => '#1a1a1a',
    ];

    /* =======================================================================
     | RELATIONSHIPS
     * ===================================================================== */

    /**
     * The user who owns this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =======================================================================
     | ACCESSORS
     * ===================================================================== */

    /**
     * $profile->header_image_url
     *
     * Exposes a READY-TO-RENDER URL for the Blade templates and the Alpine
     * preview. The stored column holds a RELATIVE path ("headers/xyz.jpg"),
     * never a full URL, because absolute URLs would break when the app moves
     * between domains (local → staging → production) and would leak the
     * storage host into the database dump.
     *
     * ★ Laravel Attribute gotcha handled here ★
     * The method name `headerImageUrl()` tells Laravel this accessor serves a
     * virtual attribute called `header_image_url` — which is NOT a database
     * column. The getter therefore receives null as its $value, NOT the
     * contents of the `header_image` column. That is exactly why the getter
     * declares the SECOND parameter, $attributes (the model's full raw
     * attribute map), and reads the real source column from it. If you only
     * declare ($value), the accessor silently takes its fallback branch
     * forever — the #1 silent bug with the modern Attribute syntax.
     *
     * Fallback strategy (in order):
     *  1. Stored path exists on the public disk  → storage URL
     *  2. Stored path missing (file deleted)     → stock cover image
     *  3. No path at all (new profile)           → stock cover image
     * The fallback prevents the classic "broken image icon" when an upload
     * was removed from disk manually.
     */
    protected function headerImageUrl(): Attribute
    {
        return Attribute::get(function (?string $value, array $attributes): string {
            // The REAL source column (see gotcha note above).
            $path = $attributes['header_image'] ?? null;

            $fallback = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=800&auto=format&fit=crop';

            if ($path && Storage::disk('public')->exists($path)) {
                // Storage::url() respects the disk's base URL configuration,
                // so this works with local disks AND S3 without code changes.
                return Storage::disk('public')->url($path);
            }

            return $fallback;
        });
    }
}
