<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Language;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();

        foreach ([
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
        ] as $code => $name) {
            Language::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        foreach (['mobile', 'desktop', 'web'] as $slug) {
            Tag::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug)],
            );
        }
    }

    /**
     * Creates the admin once; an existing admin's password is never reset.
     * Production refuses the well-known default password.
     */
    private function seedAdmin(): void
    {
        $password = config('translations.seed.admin_password');
        if (! is_string($password) || $password === '') {
            if (app()->isProduction()) {
                $this->command?->warn('SEED_ADMIN_PASSWORD is not set; skipping the admin user.');

                return;
            }

            $password = 'password';
        }

        $admin = User::query()->firstOrNew(['email' => (string) config('translations.seed.admin_email')]);
        if (! $admin->exists) {
            $admin->forceFill([
                'name' => 'Administrator',
                'password' => $password,
                'role' => UserRole::Editor,
            ])->save();
        }
    }
}
