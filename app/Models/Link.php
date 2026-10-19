<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * Same isolation rule as Profile: `user_id` is NOT fillable. Links are
     * always created through $user->links()->create([...]) or
     * $user->links()->make([...]), which inject the owner FK implicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'url',
        'order',
        'is_active',
    ];

    /**
     * Casts: `order` is stored as an unsigned integer and `is_active` as a
     * real boolean so Blade/Alpine can use it directly in conditions without
     * loose "0"/"1" string comparisons.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /* =======================================================================
     | RELATIONSHIPS
     * ===================================================================== */

    /**
     * The user who owns this link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
