<?php

namespace Tests\Feature;

use App\Enum\RoleEnum;
use App\Enum\UserStatusEnum;
use App\Models\User;
use App\Services\EmailVerificationService;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\AnaInsan\AnaInsanTestCase;

class AdminUserSeederTest extends AnaInsanTestCase
{
    public function test_seeded_admin_can_sign_in_and_access_administration_without_email_verification(): void
    {
        $this->seed(AdminUserSeeder::class);
        $user = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->assertSame('Admin', $user->name);
        $this->assertSame(UserStatusEnum::ACTIVE, $user->status);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Admin20262027$$', $user->password));
        $this->assertNotSame('Admin20262027$$', $user->password);

        $response = $this->api('POST', 'auth/login', [
            'email' => 'admin@admin.com',
            'password' => 'Admin20262027$$',
        ])->assertOk()->assertJsonPath('data.user.roles.0', RoleEnum::ADMIN->value);
        $this->api('GET', 'admin/access', [], $response->json('data.access_token'))->assertOk();
        Notification::assertNothingSent();
    }

    public function test_seeding_again_keeps_one_admin_and_does_not_change_other_users(): void
    {
        $donor = $this->user();
        $original = $donor->getRawOriginal();

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@admin.com')->count());
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $this->assertSame(1, $admin->roles()->where('name', RoleEnum::ADMIN->value)->count());
        $this->assertTrue(Hash::check('Admin20262027$$', $admin->password));
        $this->assertSame($original, $donor->fresh()->getRawOriginal());
        $this->assertFalse($donor->fresh()->hasRole(RoleEnum::ADMIN));
    }

    public function test_existing_account_receives_the_requested_credentials_and_old_access_is_revoked(): void
    {
        $user = $this->user('donor', false);
        $user->update(['email' => 'admin@admin.com']);
        $token = $this->token($user);
        $resetToken = Password::createToken($user);
        app(EmailVerificationService::class)->send($user);

        $this->seed(AdminUserSeeder::class);

        $user->refresh();
        $this->assertSame('Admin', $user->name);
        $this->assertTrue($user->hasRole(RoleEnum::ADMIN));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Admin20262027$$', $user->password));
        $this->assertNull($user->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse($user->verificationCode()->exists());
        $this->assertFalse(Password::tokenExists($user, $resetToken));
        $this->api('GET', 'admin/access', [], $token)->assertUnauthorized();
    }
}
