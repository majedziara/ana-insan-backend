<?php

namespace Database\Seeders;

use App\Models\NewsItem;
use Illuminate\Database\Seeder;

class NewsItemSeeder extends Seeder
{
    public function run(): void
    {
        NewsItem::firstOrCreate(['text' => 'أهلًا بكم في موقع فريق أنا إنسان التطوعي. معًا نمدّ يد العون ونصنع الأمل.'], [
            'text_en' => 'Welcome to Ana Insan Volunteer Team. Together, we lend a hand and bring hope.',
            'duration_days' => 7,
            'is_active' => true,
            'published_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
