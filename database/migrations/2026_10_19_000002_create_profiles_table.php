<?php

/**
 * ---------------------------------------------------------------------------
 * PHASE 1 — MIGRATION 2/3: Create the `profiles` table
 * ---------------------------------------------------------------------------
 * WHY a separate table instead of widening `users`?
 *  1. Single Responsibility — `users` owns AUTHENTICATION credentials only
 *     (identity); `profiles` owns PRESENTATION (everything rendered on the
 *     public bio page). Credential rows are read on every request via the
 *     session; profile rows are read only when rendering pages → separating
 *     them keeps the hot auth table narrow.
 *  2. Extensibility — future presentation fields (social icons, themes,
 *     fonts) land in `profiles` without touching auth code or risk of
 *     breaking the login flow.
 *  3. 1:1 cardinality is still enforced by a UNIQUE index on `user_id`,
 *     because `constrained()` alone only creates a plain FK, not a 1:1 rule.
 *
 * COLOR COLUMNS store 7-char hex strings ("#rrggbb"). The DB keeps them as
 * plain strings; format validity is enforced by a strict regex in the Form
 * Request (Phase 3) — validating in ONE place (the request) avoids the classic
 * "validation logic scattered across model casts, observers and controllers"
 * anti-pattern.
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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            // Owning user. `constrained()` resolves the table name + FK to
            // users.id automatically; `cascadeOnDelete()` keeps the classic
            // "account deletion ⇒ profile deletion" invariant enforced by the
            // database itself rather than by possibly-forgotten app code.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ---- Public presentation fields --------------------------------
            $table->string('display_name', 60)->nullable();   // shown as the H1
            $table->string('bio', 160)->nullable();           // subtitle line
            $table->string('header_image')->nullable();       // relative path on
                                                              // the "public" disk,
                                                              // e.g. headers/xyz.jpg
            $table->string('bg_color_1', 7)->default('#b88c8f'); // gradient top
            $table->string('bg_color_2', 7)->default('#ba8b8f'); // gradient bottom
            $table->string('text_color', 7)->default('#1a1a1a'); // global text color

            $table->timestamps();

            // Enforce the 1:1 relationship at the storage layer.
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
