<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_menu_links_to_the_profile_page(): void
    {
        $this->actingAs(User::factory()->create());

        $profileUrl = Filament::getProfileUrl();

        $this->assertNotEmpty($profileUrl);
        $this->get('/admin')->assertOk()->assertSee($profileUrl, false);
        $this->get($profileUrl)->assertOk();
    }

    public function test_name_can_be_changed_without_the_current_password(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertFormSet(['name' => 'Old Name', 'email' => $user->email])
            ->fillForm(['name' => 'New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Name', $user->refresh()->name);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'original-pass']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'changed@ampsquad.test'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertNotSame('changed@ampsquad.test', $user->refresh()->email);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'changed@ampsquad.test', 'currentPassword' => 'original-pass'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('changed@ampsquad.test', $user->refresh()->email);
    }

    public function test_password_can_be_changed_with_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'original-pass']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'Brand-new-pass-1',
                'passwordConfirmation' => 'Brand-new-pass-1',
                'currentPassword' => 'original-pass',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('Brand-new-pass-1', $user->refresh()->password));
    }

    public function test_password_change_is_rejected_with_a_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'original-pass']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'Brand-new-pass-1',
                'passwordConfirmation' => 'Brand-new-pass-1',
                'currentPassword' => 'wrong-pass',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('original-pass', $user->refresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create(['password' => 'original-pass']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'Brand-new-pass-1',
                'passwordConfirmation' => 'something-else',
                'currentPassword' => 'original-pass',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(Filament::getProfileUrl() ?? '/admin/profile')->assertRedirect();
    }
}
