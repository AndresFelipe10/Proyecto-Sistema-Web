<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. GET /login contiene <title>Iniciar Sesión | config('app.name')</title>
     */
    public function test_login_page_contains_expected_brand_title(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $expectedTitle = '<title>Iniciar Sesión | ' . config('app.name') . '</title>';
        $response->assertSee($expectedTitle, false);
    }

    /**
     * 2. config('app.name') es PuntoStock
     */
    public function test_app_name_configuration_is_puntostock(): void
    {
        $this->assertSame('PuntoStock', config('app.name'));
    }

    /**
     * 3. Las páginas guest muestran "PuntoStock" y no muestran "Cali Emprende"
     */
    public function test_guest_pages_display_puntostock_and_do_not_display_cali_emprende(): void
    {
        $guestRoutes = [
            '/login',
            '/forgot-password',
        ];

        foreach ($guestRoutes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee('PuntoStock');
            $response->assertDontSee('Cali Emprende');
        }
    }

    /**
     * 4. La página de login incluye los <link rel="icon"> (svg, ico, 32, 16) y el apple-touch-icon
     */
    public function test_login_page_includes_brand_favicons_and_apple_touch_icon(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<link rel="icon" type="image/svg+xml" href="' . asset('favicon.svg') . '?v=1">', false);
        $response->assertSee('<link rel="icon" href="' . asset('favicon.ico') . '?v=1" sizes="48x48">', false);
        $response->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="' . asset('favicon-32x32.png') . '?v=1">', false);
        $response->assertSee('<link rel="icon" type="image/png" sizes="16x16" href="' . asset('favicon-16x16.png') . '?v=1">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="' . asset('apple-touch-icon.png') . '?v=1">', false);
        $response->assertSee('<meta name="theme-color" content="#1B2A49">', false);
    }

    /**
     * 5. Existen public/favicon.ico, public/favicon.svg, public/apple-touch-icon.png y public/images/brand/logo-mark.svg
     */
    public function test_brand_assets_exist_in_public_directory(): void
    {
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertFileExists(public_path('favicon-16x16.png'));
        $this->assertFileExists(public_path('favicon-32x32.png'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileExists(public_path('images/brand/logo-mark.svg'));
    }
}
