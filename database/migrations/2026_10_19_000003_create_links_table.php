<?php

/**
 * ---------------------------------------------------------------------------
 * PHASE 1 — MIGRATION 3/3: Create the `links` table
 * ---------------------------------------------------------------------------
 * WHY a `links` table instead of a JSON column on `profiles`?
 *  - Each link is an independent entity that will later grow behaviour
 *    (click analytics, per-link icons, scheduling). Rows scale; JSON blobs
 *    force full-row rewrites and cannot be indexed per item.
 *
 * GOTCHA — `order` is a SQL KEYWORD (ORDER BY). Laravel wraps every column
 * identifier in backticks (`order`), so it works on MySQL and SQLite. We
 * additionally ship an explicit Eloquent `orderBy('order')` scope in the
 * controllers so nobody has to remember the syntax.
 *
 * COMPOSITE INDEX (user_id, order): the public bio page always runs the query
 * `WHERE user_id = ? ORDER BY order`. A composite index lets MySQL satisfy
 * both the filter AND the sort from the index alone ("using index"), avoiding
 * a filesort on every page view.
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
        Schema::create('links', function (Blueprint $table) {
            $table->id();

            // Owner. Cascade ⇒ deleting a user cleans up all their links in
            // the same implicit FK context (no orphan rows, no manual GC).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title', 80);      // button label shown to visitors
            $table->string('url', 2048);      // generous: URLs with long UTM
                                              // tracking chains are common
            $table->unsignedInteger('order')->default(0); // manual sort position
            $table->boolean('is_active')->default(true);  // soft "publish" switch:
                                                          // hidden links stay in
                                                          // DB, not rendered

            $table->timestamps();

            // Composite index tuned for the public bio query (see header).
            $table->index(['user_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
