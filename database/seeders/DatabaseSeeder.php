<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates ONE demo account whose bio page matches the original static
     * prototype (resources/prototypes/Bio Page.html), so the app is visually
     * verifiable the moment it boots:
     *
     *   Public page : http://127.0.0.1:8000/hadeel
     *   Dashboard   : http://127.0.0.1:8000/login
     *                 → test@example.com / password
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'هديل أبو صفية',
            'username' => 'hadeel',   // lowercased by the model mutator anyway
            'email' => 'test@example.com',
        ]);

        // ---- Profile: colours mirror the prototype's dusty-rose gradient ----
        $user->profile()->create([
            'display_name' => 'هديل أبو صفية',
            'bio' => 'Software Engineer & Graphic Designer',
            'header_image' => null, // Cover images are optional.
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
        ]);

        // ---- Links: seeded through the RELATIONSHIP (never a raw user_id) --
        // `order` is assigned from the array index so the public page renders
        // in exactly this sequence.
        $links = [
            ['title' => 'معرض أعمالي على بيهانس', 'url' => 'https://behance.net'],
            ['title' => 'حسابي على GitHub ومشاريعي البرمجية', 'url' => 'https://github.com'],
            ['title' => 'ملفي الشخصي على LinkedIn', 'url' => 'https://linkedin.com'],
        ];

        foreach ($links as $index => $link) {
            $user->links()->create([
                'title' => $link['title'],
                'url' => $link['url'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
