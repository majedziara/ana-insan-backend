<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

class SynchronousExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = RolesAndPermissionsSeeder::class;

    public function test_registration_sends_verification_mail_before_the_response_without_queueing(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->registerDonor();

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
        $this->assertStringContainsString('أنا إنسان', $transport->messages()->first()->getOriginalMessage()->getSubject());
        $this->assertDatabaseCount('email_verification_codes', 1);
        $this->assertDatabaseCount('jobs', 0);
        Queue::assertNothingPushed();
    }

    public function test_password_reset_sends_mail_in_the_request_without_queueing(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        User::factory()->create(['email' => 'reset@example.test', 'preferred_locale' => 'ar']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.test'])->assertOk();

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $this->assertStringContainsString('أنا إنسان', $transport->messages()->first()->getOriginalMessage()->getSubject());
        $this->assertDatabaseCount('jobs', 0);
        Queue::assertNothingPushed();
    }

    public function test_transport_acceptance_is_logged_without_message_contents_or_credentials(): void
    {
        Log::spy();
        config(['mail.mailers.smtp.password' => 'smtp-test-secret']);
        User::factory()->create(['email' => 'recipient@example.test']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'recipient@example.test'])->assertOk();

        Log::shouldHaveReceived('info')->once()->with('mail.transport_accepted', Mockery::on(function (array $context): bool {
            $this->assertSame('array', $context['mailer']);
            $this->assertSame('api/v1/auth/forgot-password', $context['request_path']);
            $this->assertNotEmpty($context['message_id']);
            $this->assertSame([hash('sha256', 'recipient@example.test')], $context['recipient_fingerprints']);
            $this->assertArrayNotHasKey('body', $context);
            $this->assertStringNotContainsString('recipient@example.test', json_encode($context));
            $this->assertStringNotContainsString('smtp-test-secret', json_encode($context));

            return true;
        }));
    }

    public function test_failed_verification_delivery_rolls_back_registration(): void
    {
        Log::spy();
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable.'));
        Mail::mailer()->setSymfonyTransport($transport);

        $this->postJson('/api/v1/auth/register', $this->registrationData())->assertServerError();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_verification_codes', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('jobs', 0);
        Log::shouldNotHaveReceived('info');
    }

    public function test_a_failed_password_reset_email_can_be_retried_immediately(): void
    {
        User::factory()->create(['email' => 'reset@example.test']);
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable.'));
        Mail::mailer()->setSymfonyTransport($transport);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.test'])->assertServerError();
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $retryTransport = new ArrayTransport;
        Mail::mailer()->setSymfonyTransport($retryTransport);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.test'])->assertOk();

        $this->assertCount(1, $retryTransport->messages());
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    private function registerDonor(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registrationData())->assertCreated();
    }

    /** @return array{name: string, email: string, password: string, password_confirmation: string, preferred_locale: string} */
    private function registrationData(): array
    {
        return [
            'name' => 'Ana Insan Donor',
            'email' => 'donor@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'preferred_locale' => 'ar',
        ];
    }
}
