<?php

namespace Tests\Feature\Admin\Http\Controllers;

use App\Auth\Mail\RegistrationInviteMail;
use App\Auth\Models\RegistrationInvite;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers();
    }

    #[Test]
    public function admins_can_view_admin_pages(): void
    {
        MuscleGroup::factory()->create(['name' => 'Chest', 'slug' => 'chest']);
        Exercise::factory()->create(['name' => 'Bench', 'slug' => 'bench', 'user_id' => null]);

        $this->actingAs($this->adminUser)->get(route('admin.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/Index'));

        $this->actingAs($this->adminUser)->get(route('admin.exercises'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Exercises')
                ->has('muscle_groups')
                ->loadDeferredProps(fn ($page) => $page->has('exercises')));

        $this->actingAs($this->adminUser)->get(route('admin.exercise-profiles'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/ExerciseProfiles')
                ->has('drafts')
                ->has('published'));

        $this->actingAs($this->adminUser)->get(route('admin.muscle-groups'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/MuscleGroups')
                ->has('muscle_groups'));

        $this->actingAs($this->adminUser)->get(route('admin.users'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Users')
                ->has('users'));

        $this->actingAs($this->adminUser)->get(route('admin.invites'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Invites')
                ->has('invites'));
    }

    #[Test]
    public function admin_exercises_page_tolerates_soft_deleted_primary_muscle_group(): void
    {
        $exercise = Exercise::query()->shared()->with('primaryMuscleGroup')->firstOrFail();
        $exercise->primaryMuscleGroup->delete();

        $this->actingAs($this->adminUser)->get(route('admin.exercises'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Exercises')
                ->loadDeferredProps(fn ($page) => $page
                    ->where(
                        'exercises',
                        function (mixed $exercises) use ($exercise): bool {
                            $rows = collect(is_iterable($exercises) ? $exercises : []);

                            return $rows->contains(
                                fn (mixed $row): bool => is_array($row)
                                    && ($row['slug'] ?? null) === $exercise->getSlug()
                                    && ($row['primary_muscle_group'] ?? null) === 'Unknown',
                            );
                        },
                    )));
    }

    #[Test]
    public function admins_can_create_and_revoke_invites(): void
    {
        Mail::fake();

        $this->actingAs($this->adminUser)
            ->post(route('admin.invites.store'), [
                'email' => 'buddy@example.com',
                'note' => 'Gym buddy',
                'role' => 'user',
                'expires_in_days' => 3,
            ])
            ->assertRedirect(route('admin.invites'))
            ->assertSessionHas('invite_url')
            ->assertSessionHas('success');

        $invite = RegistrationInvite::query()->firstOrFail();
        $this->assertSame('buddy@example.com', $invite->email);

        Mail::assertSent(RegistrationInviteMail::class, fn (RegistrationInviteMail $mail): bool => $mail->hasTo('buddy@example.com')
            && $mail->hasReplyTo($this->adminUser->email));

        $this->actingAs($this->adminUser)
            ->post(route('admin.invites.revoke', $invite->id))
            ->assertRedirect(route('admin.invites'));

        $this->assertNotNull($invite->fresh()->revoked_at);
    }

    #[Test]
    public function admins_can_resend_usable_invites(): void
    {
        Mail::fake();

        $invite = RegistrationInvite::query()->create([
            'token' => 'resend-token-'.uniqid(),
            'created_by' => $this->adminUser->id,
            'role' => 'user',
            'email' => 'again@example.com',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.invites.resend', $invite->id))
            ->assertRedirect(route('admin.invites'))
            ->assertSessionHas('success');

        Mail::assertSent(RegistrationInviteMail::class, fn (RegistrationInviteMail $mail): bool => $mail->hasTo('again@example.com'));
    }

    #[Test]
    public function invite_store_requires_email(): void
    {
        Mail::fake();

        $this->actingAs($this->adminUser)
            ->post(route('admin.invites.store'), [
                'note' => 'Missing email',
                'role' => 'user',
                'expires_in_days' => 3,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, RegistrationInvite::query()->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function non_admins_cannot_view_admin_pages(): void
    {
        $this->actingAs($this->user)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.exercises'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.exercise-profiles'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.muscle-groups'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.invites'))->assertForbidden();
    }

    #[Test]
    public function admins_can_create_edit_and_publish_an_immutable_preset(): void
    {
        $payload = [
            'name' => 'Power',
            'target_reps' => 5,
            'floor_override' => null,
            'working_rest_seconds' => 180,
            'warm_up_steps' => [
                ['percent' => 50, 'reps' => 5],
                ['percent' => 75, 'reps' => 3],
            ],
        ];

        $this->actingAs($this->adminUser)
            ->post(route('admin.exercise-profiles.store'), $payload)
            ->assertRedirect(route('admin.exercise-profiles'));

        $draft = ExerciseProfile::query()->where('name', 'Power')->firstOrFail();
        $this->assertSame(ExerciseProfileStatus::Draft, $draft->status);
        $this->assertNull($draft->slug);

        $this->actingAs($this->adminUser)
            ->put(route('admin.exercise-profiles.update', $draft), [...$payload, 'target_reps' => 6])
            ->assertRedirect(route('admin.exercise-profiles'));

        $draft->refresh();
        $this->assertSame(6, $draft->target_reps);

        $this->actingAs($this->adminUser)
            ->post(route('admin.exercise-profiles.publish', $draft))
            ->assertRedirect(route('admin.exercise-profiles'));

        $published = $draft->fresh();
        $this->assertSame(ExerciseProfileStatus::Published, $published->status);
        $this->assertSame('preset-power', $published->slug);

        $this->actingAs($this->adminUser)
            ->put(route('admin.exercise-profiles.update', $published), $payload)
            ->assertForbidden();
    }

    #[Test]
    public function admins_cannot_publish_a_duplicate_preset_recipe(): void
    {
        $strength = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $draft = ExerciseProfile::factory()->preset()->draft()->withRecipe($strength->recipe())->create([
            'name' => 'Heavy Strength',
            'slug' => null,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.exercise-profiles.publish', $draft))
            ->assertSessionHasErrors('profile');

        $this->assertSame(ExerciseProfileStatus::Draft, $draft->fresh()->status);
    }

    #[Test]
    public function non_admins_cannot_create_or_revoke_invites(): void
    {
        Mail::fake();

        $invite = RegistrationInvite::query()->create([
            'token' => 'locked-token-'.uniqid(),
            'created_by' => $this->adminUser->id,
            'role' => 'user',
            'email' => 'locked@example.com',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.invites.store'), [
                'email' => 'Nope@example.com',
                'note' => 'Nope',
                'role' => 'user',
                'expires_in_days' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('admin.invites.revoke', $invite->id))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('admin.invites.resend', $invite->id))
            ->assertForbidden();

        $this->assertNull($invite->fresh()->revoked_at);
        $this->assertSame(1, RegistrationInvite::query()->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function invite_store_rejects_invalid_role(): void
    {
        Mail::fake();

        $this->actingAs($this->adminUser)
            ->post(route('admin.invites.store'), [
                'email' => 'bad-role@example.com',
                'note' => 'Bad role',
                'role' => 'superadmin',
                'expires_in_days' => 3,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(0, RegistrationInvite::query()->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
    }
}
