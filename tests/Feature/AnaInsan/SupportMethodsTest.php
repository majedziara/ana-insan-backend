<?php

namespace Tests\Feature\AnaInsan;

use App\Models\Setting;
use App\Services\WebsiteSettingsService;
use Database\Seeders\SupportMethodsSeeder;
use Illuminate\Http\UploadedFile;

class SupportMethodsTest extends AnaInsanTestCase
{
    private function configureSupport(): void
    {
        app(WebsiteSettingsService::class)->save(['settings' => [
            'gofundme_enabled' => true,
            'gofundme_url' => 'https://www.gofundme.com/f/test-campaign',
            'bank_transfer_enabled' => true,
            'bank_name' => 'Test Bank',
            'bank_beneficiary_name' => 'Test beneficiary',
            'bank_iban_ils' => 'PS35TEST000000000000000000001',
            'bank_iban_usd' => 'PS08TEST000000000000000000002',
            'bank_iban_eur' => 'PS78TEST000000000000000000003',
            'bank_iban_jod' => 'PS51TEST000000000000000000004',
        ]]);
    }

    public function test_new_install_has_no_inherited_donation_accounts(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $response = $this->api('GET', 'public/settings')->assertOk()
            ->assertJsonPath('data.settings.gofundme_enabled', false)
            ->assertJsonPath('data.settings.bank_transfer_enabled', false)
            ->assertJsonPath('data.settings.gofundme_url', null)
            ->assertJsonPath('data.settings.bank_beneficiary_name', null)
            ->assertJsonPath('data.settings.instagram_url', 'https://www.instagram.com/anainsanteam/');
        foreach (config('website.bank_currencies') as $currency) {
            $response->assertJsonPath('data.settings.bank_iban_'.strtolower($currency), null);
        }
    }

    public function test_support_changes_require_permission_and_do_not_create_payments(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $this->configureSupport();
        $payload = ['settings' => ['gofundme_enabled' => false]];
        $this->api('PATCH', 'admin/settings', $payload)->assertUnauthorized();
        $this->api('PATCH', 'admin/settings', $payload, $this->token($this->user()))->assertForbidden();
        $this->api('PATCH', 'admin/settings', $payload, $this->token($this->user('admin')))->assertOk();
        $this->api('GET', 'public/settings')->assertOk()
            ->assertJsonPath('data.settings.gofundme_enabled', false)
            ->assertJsonPath('data.settings.bank_iban_jod', 'PS51TEST000000000000000000004');
        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_iban_normalization_checksum_and_partial_patch_consistency(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $this->configureSupport();
        $token = $this->token($this->user('admin'));
        $this->api('PATCH', 'admin/settings', ['settings' => ['bank_iban_usd' => 'ps08test000000000000000000002']], $token)
            ->assertOk()->assertJsonPath('data.settings.bank_iban_usd', 'PS08TEST000000000000000000002');
        $this->api('PATCH', 'admin/settings', ['settings' => ['bank_iban_usd' => 'PS00TEST000000000000000000002']], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('settings.bank_iban_usd');
        $this->api('PATCH', 'admin/settings', ['settings' => ['bank_beneficiary_name' => null]], $token)->assertUnprocessable();
        $this->api('PATCH', 'admin/settings', ['settings' => ['gofundme_url' => null]], $token)->assertUnprocessable();
        $this->api('PATCH', 'admin/settings', ['settings' => ['gofundme_enabled' => false, 'gofundme_url' => null]], $token)->assertOk();
    }

    public function test_links_are_restricted_to_actual_gofundme_domains(): void
    {
        $token = $this->token($this->user('admin'));
        foreach (['https://gofundme.com.evil.test/f/test', 'https://gofund.me@evil.test/test', 'javascript:alert(1)', 'http://gofund.me/dfb837806', 'https://gofundme.com/', 'https://www.gofundme.com/f/../x'] as $link) {
            $this->api('PATCH', 'admin/settings', ['settings' => ['gofundme_url' => $link]], $token)
                ->assertUnprocessable()->assertJsonValidationErrors('settings.gofundme_url');
        }
        $this->api('PATCH', 'admin/settings', ['settings' => ['gofundme_url' => 'https://www.gofundme.com/f/ana-insan']], $token)->assertOk();
    }

    public function test_seed_is_safe_to_repeat_and_preserves_existing_settings(): void
    {
        $record = app(WebsiteSettingsService::class)->record();
        $record->update(['value' => ['contact_email' => 'hello@example.org']]);
        $this->seed(SupportMethodsSeeder::class);
        $token = $this->token($this->user('admin'));
        $this->api('PATCH', 'admin/settings', ['settings' => ['gofundme_enabled' => false, 'bank_swift' => 'ABCDPS22']], $token)->assertOk();
        $this->seed(SupportMethodsSeeder::class);
        $this->api('GET', 'public/settings')->assertOk()
            ->assertJsonPath('data.settings.gofundme_enabled', false)
            ->assertJsonPath('data.settings.contact_email', 'hello@example.org')
            ->assertJsonPath('data.settings.bank_swift', 'ABCDPS22');
        $this->assertSame(1, Setting::where('key', config('website.settings_key'))->count());
    }

    public function test_unverified_and_suspended_administrators_cannot_change_support_settings(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $this->configureSupport();
        $unverified = $this->user('admin', false);
        $suspended = $this->user('admin');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $payload = ['settings' => ['bank_transfer_enabled' => false]];

        foreach ([$unverified, $suspended] as $user) {
            $this->api('PATCH', 'admin/settings', $payload, $this->token($user))->assertForbidden();
        }

        $this->assertTrue(app(WebsiteSettingsService::class)->values(app(WebsiteSettingsService::class)->get())['bank_transfer_enabled']);
    }

    public function test_clearing_all_accounts_requires_disabling_bank_transfers_and_failed_changes_are_atomic(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $this->configureSupport();
        $token = $this->token($this->user('admin'));
        $accounts = [];
        foreach (config('website.bank_currencies') as $currency) {
            $accounts['bank_iban_'.strtolower($currency)] = null;
        }
        $before = app(WebsiteSettingsService::class)->get()->value;

        $this->api('PATCH', 'admin/settings', ['settings' => $accounts + ['phone' => 'Must not be saved']], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('settings.bank_iban_ils');
        $this->assertSame($before, app(WebsiteSettingsService::class)->get()->value);

        $this->api('PATCH', 'admin/settings', ['settings' => $accounts + ['bank_transfer_enabled' => false]], $token)
            ->assertOk()->assertJsonPath('data.settings.bank_transfer_enabled', false)
            ->assertJsonPath('data.settings.bank_iban_ils', null);
    }

    public function test_support_seed_and_partial_saves_preserve_website_images_and_explicitly_cleared_values(): void
    {
        $service = app(WebsiteSettingsService::class);
        $service->image('logo', UploadedFile::fake()->image('logo.png'));
        $service->image('favicon', UploadedFile::fake()->image('favicon.png'));
        $setting = $service->get();
        $images = $setting->value['_images'];
        $setting->update(['value' => array_replace($setting->value, ['gofundme_enabled' => false, 'gofundme_url' => null])]);

        $this->seed(SupportMethodsSeeder::class);
        $this->api('PATCH', 'admin/settings', ['settings' => ['bank_swift' => 'abcdps22']], $this->token($this->user('admin')))
            ->assertOk()->assertJsonPath('data.settings.bank_swift', 'ABCDPS22');
        $this->seed(SupportMethodsSeeder::class);

        $this->assertSame($images, $service->get()->value['_images']);
        $this->assertNull($service->get()->value['gofundme_url']);
        $this->assertFalse($service->get()->value['gofundme_enabled']);
        foreach (['logo', 'favicon'] as $asset) {
            $this->get('/api/v1/public/settings/images/'.$asset)->assertOk()->assertHeader('Content-Type', 'image/png');
        }
    }

    public function test_support_data_is_available_in_both_public_languages_without_expanding_payment_currencies(): void
    {
        $this->seed(SupportMethodsSeeder::class);
        $this->configureSupport();

        foreach (['ar', 'en'] as $locale) {
            $this->getJson('/api/v1/public/home', ['Accept-Language' => $locale])->assertOk()
                ->assertJsonPath('data.settings.settings.gofundme_enabled', true)
                ->assertJsonPath('data.settings.settings.bank_transfer_enabled', true)
                ->assertJsonPath('data.settings.settings.bank_iban_jod', 'PS51TEST000000000000000000004')
                ->assertJsonPath('data.settings.settings.bank_swift', null)
                ->assertJsonPath('data.settings.settings.bank_beneficiary_name_en', null);
        }
        $token = $this->token($this->user('admin'));
        $this->api('PATCH', 'admin/settings', ['settings' => ['display_currencies' => ['JOD']]], $token)
            ->assertUnprocessable()->assertJsonValidationErrors('settings.display_currencies.0');
        $this->api('PATCH', 'admin/settings', ['settings' => ['donations_enabled' => false]], $token)->assertOk();
        $this->assertFalse(app(WebsiteSettingsService::class)->acceptsDonations());
        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
