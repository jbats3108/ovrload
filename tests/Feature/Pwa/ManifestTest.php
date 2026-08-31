<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class ManifestTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(withCatalogAndRoutines: false);
    }

    #[Test]
    public function it_has_a_web_app_manifest_on_disk(): void
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $json = file_get_contents($path);
        $this->assertNotFalse($json);

        $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('OVRLOAD', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }

    #[Test]
    public function it_includes_pwa_meta_tags_in_the_app_shell(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('rel="manifest" href="/manifest.webmanifest"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
        $response->assertSee('name="apple-mobile-web-app-title" content="OVRLOAD"', false);
        $response->assertInertia(fn ($page) => $page->where('name', 'OVRLOAD'));
    }

    #[Test]
    public function it_emits_the_service_worker_at_the_web_root_when_built(): void
    {
        $path = public_path('sw.js');

        if (! is_file($path)) {
            $this->markTestSkipped('Run `npm run build` to emit public/sw.js.');
        }

        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('/build/', $contents);
        $this->assertStringNotContainsString('navigateFallback', $contents);
    }
}
