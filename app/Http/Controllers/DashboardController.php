<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Support\Base64Image;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * ---------------------------------------------------------------------------
 * PHASE 3 — DashboardController (PRIVATE, auth-only)
 * ---------------------------------------------------------------------------
 * ★ ZERO-TRUST SECURITY RULE (enforced throughout this class) ★
 *
 *   Under NO circumstances does any method here read `user_id` from the HTTP
 *   request body/query string. Every mutation is scoped to
 *   $request->user() — i.e. the session-authenticated identity established
 *   by Laravel's session guard. Even if an attacker crafts a request with
 *   `user_id=1`, that key is simply never read: it is ignored, not trusted.
 *
 *   This eliminates the entire class of IDOR (Insecure Direct Object
 *   Reference) vulnerabilities that plagues CRUD apps.
 * ---------------------------------------------------------------------------
 */
class DashboardController extends Controller
{
    /**
     * Render the editor dashboard for the AUTHENTICATED user.
     *
     * LIFECYCLE:
     *  1. Eager-load profile + links (one query each — no N+1).
     *  2. If no profile row exists yet (brand-new account), build an UNSAVED
     *     in-memory instance via ->make() so the Alpine live preview starts
     *     with the migration's default palette instead of empty colour
     *     pickers. Nothing is persisted until the user submits the form.
     */
    public function edit(): View
    {
        /** @var User $user */
        $user = request()->user()->load([
            'profile',
            'links' => fn ($query) => $query->orderBy('order'),
        ]);

        return view('dashboard', [
            // make() ⇒ unsaved model pre-populated with the $attributes
            // defaults defined in Profile (Phase 2). Saving later goes
            // through the relationship: $user->profile()->save($profile),
            // which injects user_id implicitly — the request never supplies it.
            'profile' => $user->profile ?? $user->profile()->make(),
            'links' => $user->links, // already sorted by `order`
        ]);
    }

    /**
     * Persist profile changes, the optional header image, and the full link
     * set. Profile and link database writes are one atomic operation; file
     * changes are coordinated around that transaction with compensating cleanup.
     *
     * TRANSACTIONALITY: without DB::transaction(), a failure while syncing
     * links would leave the colours saved but the links half-updated — an
     * inconsistent state the user cannot easily repair from the UI.
     *
     * IMAGE LIFECYCLE: store the new file first, commit its path with the
     * database changes, then delete the old file. On rollback, remove the new
     * upload and keep the old file referenced by the unchanged profile.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        // ZERO-TRUST ANCHOR: identity comes from the SESSION, never the body.
        $user = $request->user();

        // validated() only exposes keys declared in UpdateProfileRequest::rules()
        // — any smuggled user_id / is_admin / etc. key is stripped automatically.
        $data = $request->validated();
        $newHeaderPath = null;
        $oldHeaderPath = null;
        $shouldDeleteOldHeader = false;

        try {
            DB::transaction(function () use ($user, $data, $request, &$newHeaderPath, &$oldHeaderPath, &$shouldDeleteOldHeader) {
                /* ---------------------------- 1. PROFILE ------------------------- */
                $profile = $user->profile ?? $user->profile()->make();
                $oldHeaderPath = $profile->header_image;

                // ---- Header image (optional: replace, remove, or leave as-is) ----
                if ($request->hasFile('header_image')) {
                    // store() generates a UUID-based name → no filename collisions,
                    // no user-controlled paths, no original-name XSS surface.
                    $newPath = $request->file('header_image')->store('headers', 'public');

                    // store() returns false on disk failure — abort triggers rollback.
                    abort_if($newPath === false, 500, 'تعذر حفظ صورة الهيدر.');
                    $newHeaderPath = $newPath;

                    $profile->header_image = $newPath; // relative path, see accessor
                } elseif (is_string($request->input('header_image_base64')) && $request->input('header_image_base64') !== '') {
                    // Multipart uploads are rejected by the production host's edge
                    // proxy (raw 403), so the dashboard ships the cover as a
                    // base64 data URL inside the normal urlencoded POST instead.
                    $image = Base64Image::decode($request->input('header_image_base64'));

                    abort_if($image === null, 422, 'صورة الهيدر غير صالحة.');
                    abort_if(strlen($image['bytes']) > Base64Image::MAX_BYTES, 422, 'حجم صورة الهيدر يتجاوز 2 ميغابايت.');

                    $newPath = 'headers/'.Str::uuid().'.'.$image['ext'];

                    abort_unless(
                        Storage::disk('public')->put($newPath, $image['bytes']) !== false,
                        500,
                        'تعذر حفظ صورة الهيدر.'
                    );

                    $newHeaderPath = $newPath;
                    $profile->header_image = $newPath;
                } elseif ($request->boolean('remove_header_image')) {
                    // Cover is OPTIONAL: no replacement file + explicit removal flag
                    // ⇒ clear the column; the old file is deleted after commit.
                    $profile->header_image = null;
                }

                $shouldDeleteOldHeader = $oldHeaderPath !== $profile->header_image;

                $profile->fill(collect($data)->only([
                    'display_name', 'bio', 'bg_color_1', 'bg_color_2', 'text_color',
                    'page_background_color',
                ])->all());

                // SAVE THROUGH THE RELATIONSHIP ⇒ user_id injected internally.
                $user->profile()->save($profile);

                /* ----------------------------- 2. LINKS --------------------------- */
                $this->syncLinks($user, $data['links'] ?? []);
            });
        } catch (Throwable $exception) {
            if ($newHeaderPath !== null
                && Storage::disk('public')->exists($newHeaderPath)
                && ! Storage::disk('public')->delete($newHeaderPath)) {
                Log::warning('Failed to clean up a profile image after a failed database update.', [
                    'path' => $newHeaderPath,
                ]);
            }

            throw $exception;
        }

        if ($shouldDeleteOldHeader && $oldHeaderPath
            && Storage::disk('public')->exists($oldHeaderPath)
            && ! Storage::disk('public')->delete($oldHeaderPath)) {
            throw new \RuntimeException('The profile was saved, but the previous image could not be removed.');
        }

        // with() flashes status into the session for the redirected GET.
        return redirect()
            ->route('dashboard.edit')
            ->with('status', 'تم حفظ التغييرات ونشرها بنجاح ✨');
    }

    /**
     * Sync the submitted link array with the database (diff-based).
     *
     * ALGORITHM (the "diff sync" pattern — the backbone of most CMS editors):
     *  1. Collect the ids the client wants to KEEP.
     *  2. DELETE every row of THIS user that is missing from that list
     *     (rows the user removed in the UI).
     *  3. Upsert the rest: known id ⇒ UPDATE, unknown/blank id ⇒ INSERT.
     *  4. Position = array index ⇒ reordering is preserved for free.
     *
     * OWNERSHIP HARDENING: ids are always resolved through
     * $user->links()->whereKey(...) — the user_id predicate is part of the
     * query itself. A forged id belonging to ANOTHER user matches zero rows
     * and falls through to the CREATE branch instead of overwriting the
     * victim's link. This is zero-trust applied to mass sync.
     */
    private function syncLinks(User $user, array $submitted): void
    {
        $keepIds = collect($submitted)
            ->pluck('id')
            ->filter() // drops null/"" from brand-new rows
            ->map(fn ($id) => (int) $id)
            ->all();

        // (2) Diff deletion — scoped to this user, never a global delete.
        $user->links()
            ->when(! empty($keepIds), fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();

        // (3)+(4) Upsert each row at its new positional index.
        foreach (array_values($submitted) as $index => $row) {
            $attributes = [
                'title' => $row['title'],
                'url' => $row['url'],
                'order' => $index,                // array order == display order
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $existing = ! empty($row['id'])
                ? $user->links()->whereKey((int) $row['id'])->first() // ownership-scoped
                : null;

            if ($existing) {
                $existing->update($attributes);       // UPDATE owned row only
            } else {
                $user->links()->create($attributes); // INSERT under my ownership
            }
        }
    }
}
