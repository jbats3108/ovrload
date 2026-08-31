<?php

namespace Tests\Unit\Users\Services;

use App\Users\Data\UpsertPlateProfileData;
use App\Users\Models\User;
use App\Users\Services\PlateCalculatorService;
use App\Users\Services\PlateProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlateProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlateProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlateProfileService::class);
    }

    #[Test]
    public function ensure_profile_seeds_defaults_once(): void
    {
        $user = User::factory()->create();
        $defaults = PlateCalculatorService::defaultProfilePayload();

        $first = $this->service->ensureProfile($user);
        $second = $this->service->ensureProfile($user);

        $this->assertTrue($first->is($second));
        $this->assertSame($defaults['name'], $first->name);
        $this->assertCount(count($defaults['bars']), $first->bars);
        $this->assertCount(count($defaults['plates']), $first->plates);
        $this->assertSame(1, $user->plateProfile()->count());
    }

    #[Test]
    public function profile_payload_for_returns_bars_and_plates_shape(): void
    {
        $user = User::factory()->create();

        $payload = $this->service->profilePayloadFor($user);

        $this->assertArrayHasKey('name', $payload);
        $this->assertArrayHasKey('bars', $payload);
        $this->assertArrayHasKey('plates', $payload);
        $this->assertNotEmpty($payload['bars']);
        $this->assertArrayHasKey('name', $payload['bars'][0]);
        $this->assertArrayHasKey('weight_g', $payload['bars'][0]);
        $this->assertArrayHasKey('is_default', $payload['bars'][0]);
        $this->assertNotEmpty($payload['plates']);
        $this->assertArrayHasKey('denomination_g', $payload['plates'][0]);
        $this->assertArrayHasKey('count', $payload['plates'][0]);
        $this->assertArrayHasKey('colour', $payload['plates'][0]);
    }

    #[Test]
    public function upsert_replaces_bars_and_plates(): void
    {
        $user = User::factory()->create();
        $this->service->ensureProfile($user);

        $profile = $this->service->upsert($user, UpsertPlateProfileData::from([
            'name' => 'Garage',
            'bars' => [
                ['name' => 'Short', 'weight_g' => 15000, 'is_default' => true],
            ],
            'plates' => [
                ['denomination_g' => 10000, 'count' => 4, 'colour' => 'green'],
                ['denomination_g' => 5000, 'count' => 2],
            ],
        ]));

        $this->assertSame('Garage', $profile->name);
        $this->assertCount(1, $profile->bars);
        $this->assertSame(15000, $profile->bars->first()->weight_g);
        $this->assertTrue($profile->bars->first()->is_default);
        $this->assertCount(2, $profile->plates);
    }

    #[Test]
    public function upsert_forces_first_bar_default_when_none_marked(): void
    {
        $user = User::factory()->create();

        $profile = $this->service->upsert($user, UpsertPlateProfileData::from([
            'name' => 'No Default',
            'bars' => [
                ['name' => 'A', 'weight_g' => 20000, 'is_default' => false],
                ['name' => 'B', 'weight_g' => 15000, 'is_default' => false],
            ],
            'plates' => [],
        ]));

        $this->assertTrue($profile->bars->firstWhere('name', 'A')->is_default);
        $this->assertFalse($profile->bars->firstWhere('name', 'B')->is_default);
    }
}
