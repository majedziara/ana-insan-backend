<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
use App\Enum\UserStatusEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::updateOrCreate(
                ['email' => 'admin@admin.com'],
                [
                    'name' => 'Admin',
                    'password' => 'Admin20262027$$',
                    'preferred_locale' => config('anainsan.default_locale'),
                ],
            );

            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? now(),
                'status' => UserStatusEnum::ACTIVE,
                'remember_token' => null,
            ])->save();
            $user->assignRole(RoleEnum::ADMIN);
            $user->tokens()->delete();
            $user->verificationCode()->delete();
            Password::broker()->getRepository()->delete($user);
        });
    }
}
