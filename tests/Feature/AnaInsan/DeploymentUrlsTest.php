<?php

namespace Tests\Feature\AnaInsan;

use App\Providers\AppServiceProvider;
use Database\Seeders\AnaInsanPreviewSeeder;
use Illuminate\Support\Facades\Storage;

class DeploymentUrlsTest extends AnaInsanTestCase
{
    public function test_public_images_use_https_when_the_application_url_is_https(): void
    {
        Storage::fake('anainsan_private');
        $this->seed(AnaInsanPreviewSeeder::class);
        config(['app.url' => 'https://backend.example.test']);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $response = $this->getJson('http://backend.example.test/api/v1/public/home')->assertOk();

        $this->assertSame('https://backend.example.test/api/v1/public/content/1/image', $response->json('data.sections.0.image_url'));
        $this->assertStringStartsWith('https://backend.example.test/api/v1/public/cases/', $response->json('data.featured_cases.0.cover_url'));
    }

    public function test_local_http_image_urls_continue_to_work(): void
    {
        Storage::fake('anainsan_private');
        $this->seed(AnaInsanPreviewSeeder::class);
        config(['app.url' => 'http://localhost']);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $response = $this->getJson('http://localhost/api/v1/public/home')->assertOk();

        $this->assertSame('http://localhost/api/v1/public/content/1/image', $response->json('data.sections.0.image_url'));
        $this->assertStringStartsWith('http://localhost/api/v1/public/cases/', $response->json('data.featured_cases.0.cover_url'));
    }
}
