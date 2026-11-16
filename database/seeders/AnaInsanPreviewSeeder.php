<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Database\Seeders\AnaInsan\CategoriesContentSeeder;
use Database\Seeders\AnaInsan\HumanitarianCasesContentSeeder;
use Database\Seeders\AnaInsan\PartnersContentSeeder;
use Database\Seeders\AnaInsan\SeedData;
use Database\Seeders\AnaInsan\WebsiteContentSeeder;
use Database\Seeders\AnaInsan\WebsiteSettingsContentSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AnaInsanPreviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new RuntimeException('Preview content is for local/testing/staging only.');
        }
        SeedData::preflight();
        Setting::firstOrCreate(
            ['key' => SeedData::VERSION],
            ['group' => 'seeders', 'is_public' => false, 'value' => ['completed' => false]],
        );
        DB::transaction(function (): void {
            $marker = Setting::where('key', SeedData::VERSION)->lockForUpdate()->firstOrFail();
            SeedData::$running = true;
            SeedData::$categories = [];
            try {
                if (($marker->value['completed'] ?? false) === true) {
                    $restored = SeedData::restoreMissingImages();
                    $this->command?->info('Already seeded. Restored '.$restored.' missing seed images. Dashboard edits are preserved.');

                    return;
                }
                $this->call([
                    WebsiteSettingsContentSeeder::class,
                    PartnersContentSeeder::class,
                    WebsiteContentSeeder::class,
                    CategoriesContentSeeder::class,
                    HumanitarianCasesContentSeeder::class,
                ]);
                $marker->value = ['completed' => true, 'completed_at' => now()->toIso8601String()];
                $marker->save();
            } finally {
                SeedData::$running = false;
                SeedData::$categories = [];
            }
        });
    }
}
