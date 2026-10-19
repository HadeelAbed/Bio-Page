<?php

/**
 * ---------------------------------------------------------------------------
 * PHASE 1 — MIGRATION 1/3: Add `username` to the `users` table
 * ---------------------------------------------------------------------------
 * WHY: A bio-link platform is addressed by a public, human-readable handle
 * (e.g. /hadeel). E-mail is a poor public identifier: it is private, contains
 * characters we do not want in URLs, and is subject to change.
 *
 * DESIGN DECISIONS:
 *  - `unique()`      → the username IS the public primary address of a bio
 *                       page; two users can never share one. The unique index
 *                       also acts as the last line of defence against race
 *                       conditions during registration (DB-level guarantee,
 *                       not just application-level validation).
 *  - `->index()`     → satisfied by `unique()`, but we keep the column short
 *                       (30 chars) because it is looked up on EVERY public
 *                       page view; short keys stay in the InnoDB buffer pool.
 *  - Lowercasing     → enforced in TWO places for defence-in-depth:
 *                       (1) User model mutator (app layer, Phase 2)
 *                       (2) MySQL's default `utf8mb4_unicode_ci` collation
 *                           makes the unique index case-INSENSITIVE, so
 *                           "Hadeel" and "hadeel" collide at the DB level.
 *  - Allowed charset → enforced with a regex rule at validation time
 *                       (`^[a-zA-Z0-9_\-]+$`, Phase 3) so the URL stays
 *                       predictable and cannot be used for path traversal or
 *                       XSS smuggling via encoded characters.
 * ---------------------------------------------------------------------------
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Placed right after `name` for readable table dumps.
            $table->string('username', 30)
                ->unique('users_username_unique') // explicit index name keeps
                                                  // dropUnique() deterministic
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Order matters: drop the index BEFORE the column it covers.
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
