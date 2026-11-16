<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use Tests\Feature\AnaInsan\AnaInsanTestCase;

class NewsTest extends AnaInsanTestCase
{
    public function test_only_authorized_verified_users_can_manage_news(): void
    {
        $data = ['text' => 'خبر جديد من فريق أنا إنسان', 'duration_days' => 3, 'is_active' => true];
        $this->api('POST', 'admin/news', $data)->assertUnauthorized();
        foreach ([$this->user(), $this->user('member'), $this->user('admin', false)] as $user) {
            $this->api('POST', 'admin/news', $data, $this->token($user))->assertForbidden();
        }
        $this->assertDatabaseCount('news_items', 0);
    }

    public function test_news_can_be_created_edited_disabled_and_deleted(): void
    {
        $token = $this->token($this->user('admin'));
        $this->travelTo(now()->startOfSecond());
        $created = $this->api('POST', 'admin/news', ['text' => 'خبر الفريق', 'text_en' => 'Team news', 'duration_days' => 3, 'is_active' => true], $token)
            ->assertCreated()->assertJsonPath('data.duration_days', 3)
            ->assertJsonPath('data.expires_at', now()->addDays(3)->toISOString());
        $id = $created->json('data.id');
        $this->api('GET', 'admin/news', [], $token)->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->api('GET', 'admin/news/'.$id, [], $token)->assertOk()->assertJsonPath('data.text', 'خبر الفريق');
        $this->api('GET', 'public/news')->assertOk()->assertJsonPath('data.items.0.text', 'Team news');
        $this->getJson('/api/v1/public/news', ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.items.0.text', 'خبر الفريق');
        $this->api('PATCH', 'admin/news/'.$id, ['is_active' => false], $token)->assertOk();
        $this->api('GET', 'public/news')->assertOk()->assertJsonCount(0, 'data.items');
        $this->api('DELETE', 'admin/news/'.$id, [], $token)->assertOk();
        $this->assertDatabaseMissing('news_items', ['id' => $id]);
    }

    public function test_news_expires_without_a_worker_and_saving_a_duration_renews_it(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = $this->user('admin');
        $token = $this->token($admin);
        $item = NewsItem::factory()->create(['text_en' => null]);
        NewsItem::factory()->expired()->create();
        NewsItem::factory()->inactive()->create();
        NewsItem::factory()->create(['published_at' => now()->addHour()]);
        $this->api('GET', 'public/news')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.text', $item->text)->assertJsonPath('data.items.0.locale', 'ar');
        $this->travel(1)->days();
        $token = $this->token($admin);
        $this->api('GET', 'public/news')->assertOk()->assertJsonMissing(['id' => $item->id]);
        $this->api('PATCH', 'admin/news/'.$item->id, ['duration_days' => 7], $token)->assertOk()->assertJsonPath('data.expires_at', now()->addDays(7)->toISOString());
        $this->api('GET', 'public/news')->assertOk()->assertJsonFragment(['id' => $item->id, 'text' => $item->text]);
    }

    public function test_news_rejects_invalid_durations_markup_and_forged_expiry(): void
    {
        $token = $this->token($this->user('admin'));
        foreach ([0, 8, 1.5] as $days) {
            $this->api('POST', 'admin/news', ['text' => 'خبر', 'duration_days' => $days], $token)->assertUnprocessable()->assertJsonValidationErrors('duration_days');
        }
        $this->api('POST', 'admin/news', ['text' => '<script>alert(1)</script>', 'duration_days' => 1], $token)->assertUnprocessable();
        $this->api('POST', 'admin/news', ['text' => str_repeat('a', 1001), 'duration_days' => 1], $token)->assertUnprocessable();
        $this->api('POST', 'admin/news', ['text' => 'خبر', 'duration_days' => 1, 'expires_at' => now()->addYear()->toISOString()], $token)->assertUnprocessable();
        $this->assertDatabaseCount('news_items', 0);
    }

    public function test_whatsapp_settings_are_validated_normalized_and_public(): void
    {
        $token = $this->token($this->user('admin'));
        $this->api('PATCH', 'admin/settings', ['settings' => ['whatsapp_number' => '+970 (599) 000-000']], $token)->assertOk()->assertJsonPath('data.settings.whatsapp_number', '+970599000000');
        $this->api('GET', 'public/settings')->assertOk()->assertJsonPath('data.settings.whatsapp_number', '+970599000000');
        foreach (['javascript:alert(1)', '+012345678', '123', 'https://wa.me/970599000000'] as $number) {
            $this->api('PATCH', 'admin/settings', ['settings' => ['whatsapp_number' => $number]], $token)->assertUnprocessable()->assertJsonValidationErrors('settings.whatsapp_number');
        }
        $this->api('PATCH', 'admin/settings', ['settings' => ['whatsapp_number' => null]], $token)->assertOk();
        $this->api('GET', 'public/settings')->assertOk()->assertJsonPath('data.settings.whatsapp_number', null);
    }
}
