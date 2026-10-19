<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The landing route redirects to the login page (see routes/web.php:
     * `Route::get('/', ...)->name('home')`). This test pins that behaviour
     * so a future marketing homepage does not silently swallow logins.
     */
    public function test_the_application_redirects_home_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
