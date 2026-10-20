<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The landing route renders the marketing homepage (see routes/web.php:
     * `Route::get('/', ...)->name('home')`). It must link visitors to the auth
     * entry points so sign-up/login stay reachable from the page itself.
     */
    public function test_the_application_renders_the_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('تسجيل الدخول');
        $response->assertSee('ابدأ مجاناً');
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        $response->assertSee('<a href="' . route('login') . '"', false);
        $response->assertSee('<a href="' . route('register') . '"', false);
        $response->assertSee('action="' . route('register') . '" method="GET"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('pattern="[A-Za-z0-9_\-]+"', false);
        $response->assertSee('احجز رابطك');
        $response->assertSee('aspect-[34/67]', false);
        $response->assertSee('rounded-[48px] bg-black p-3', false);
    }

    public function test_reserve_link_action_opens_registration_with_username_prefilled(): void
    {
        $response = $this->get(route('register', ['username' => 'my_page']));

        $response->assertOk();
        $response->assertSee('id="username" type="text" name="username" value="my_page"', false);
    }
}
