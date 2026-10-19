<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `username` is intentionally listed so registration can create a user in
     * one call — it is still validated by the Form Request BEFORE it ever
     * reaches this array, so mass-assignment stays safe.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // never store plaintext, even if the
                                    // caller forgets Hash::make()
        ];
    }

    /* =======================================================================
     | RELATIONSHIPS (Entity Isolation)
     | Every profile/link in the system BELONGS TO exactly one user. Modelling
     | that as explicit relations (instead of passing user_id around as an
     | integer) is what allows the controllers in Phase 3 to stay zero-trust:
     | $user->links() can ONLY touch rows of that user — the FK scoping is
     | derived from the authenticated session, never from the request body.
     * ===================================================================== */

    /**
     * The user's presentation profile (1:1).
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * The user's bio links (1:∞), pre-sorted by manual order.
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /* =======================================================================
     | MUTATORS
     * ===================================================================== */

    /**
     * Force the username to lowercase + trim on EVERY write.
     *
     * Centralising normalisation in the model (rather than in each controller)
     * means registration, seeders, tinker sessions and future console
     * commands all produce identical, URL-safe values — the database's
     * case-insensitive unique index then guarantees "Hadeel" and "hadeel"
     * can never coexist.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }
}
