<?php

/**
 * ---------------------------------------------------------------------------
 * tests/load/setup-testdata.php — creates the isolated load-test accounts.
 * ---------------------------------------------------------------------------
 * WHAT IT DOES (idempotent — safe to re-run):
 *   - Ensures loadtest01..loadtest20 exist (password: loadtestpass123)
 *   - Each gets a profile + 3 active links so bio pages / dashboard have data.
 *
 * WHICH DATABASE IT WRITES TO:
 *   Whatever DB_DATABASE points at. ALWAYS run it against the STAGING DB:
 *
 *     # PowerShell (project root):
 *     $env:DB_DATABASE='bio_page_loadtest'; php tests/load/setup-testdata.php; $env:DB_DATABASE=$null
 *
 *   Never point this at production. The accounts exist solely for Scenario E
 *   (authenticated-flows.js) — 20 accounts, NOT thousands.
 * ---------------------------------------------------------------------------
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$target = config('database.connections.mysql.database');
if ($target !== 'bio_page_loadtest') {
    fwrite(STDERR, "REFUSING to run: DB_DATABASE={$target} (expected bio_page_loadtest).\n");
    exit(1);
}

$password = Hash::make('loadtestpass123');
$created = 0;

for ($i = 1; $i <= 20; $i++) {
    $username = 'loadtest' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $user = User::firstOrCreate(
        ['username' => $username],
        [
            'name'              => "Load Test User {$i}",
            'email'             => "loadtest{$i}@example.test",
            'password'          => $password,
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ]
    );

    if ($user->wasRecentlyCreated) {
        $created++;
    }

    $user->profile()->firstOrCreate([], [
        'display_name' => "Load Test User {$i}",
        'bio'          => 'Load test account for performance testing.',
        'bg_color_1'   => '#b88c8f',
        'bg_color_2'   => '#ba8b8f',
        'text_color'   => '#1a1a1a',
    ]);

    if ($user->links()->count() === 0) {
        for ($j = 0; $j < 3; $j++) {
            $user->links()->create([
                'title'     => "Link {$j}",
                'url'       => 'https://example.com',
                'order'     => $j,
                'is_active' => true,
            ]);
        }
    }
}

echo "Done. Created {$created} new account(s); 20 total available (loadtest01..loadtest20).\n";
echo "Password for all: loadtestpass123\n";
