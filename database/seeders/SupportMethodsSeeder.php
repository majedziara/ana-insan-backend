<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\WebsiteSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupportMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $record = app(WebsiteSettingsService::class)->record();
        DB::transaction(function () use ($record): void {
            $setting = Setting::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $initial = config('website.defaults');
            $setting->value = array_replace($initial, $setting->value ?? []);
            $setting->save();
        });
    }
}
