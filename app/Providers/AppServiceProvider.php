<?php

namespace App\Providers;

use App\Http\Middleware\VerifyFrontendClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $sent = $event->sent->getSymfonySentMessage();
            preg_match_all('/^\[[^\]]+\] < (250 [^\r\n]*)/m', $sent->getDebug(), $responses);

            Log::info('mail.transport_accepted', [
                'mailer' => config('mail.default'),
                'request_path' => request()->path(),
                'message_id' => $sent->getMessageId(),
                'smtp_response' => collect($responses[1])->last(),
                'recipient_fingerprints' => array_map(
                    fn (Address $address): string => hash('sha256', strtolower($address->getAddress())),
                    $sent->getEnvelope()->getRecipients(),
                ),
            ]);
        });

        if (parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers());
        RateLimiter::for('reports', fn (Request $r) => Limit::perMinute(30)->by($r->user()->id));
        RateLimiter::for('report-exports', fn (Request $r) => Limit::perMinute(3)->by($r->user()->id));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ? 'user:'.$r->user()->id : 'ip:'.$this->clientIp($r)));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($this->clientIp($r)));
        RateLimiter::for('auth-login', fn (Request $r) => [Limit::perMinute(30)->by('ip:'.$this->clientIp($r)), Limit::perMinute(5)->by('email:'.hash('sha256', Str::lower(trim(is_string($r->input('email')) ? $r->input('email') : ''))).'|'.$this->clientIp($r))]);
        RateLimiter::for('auth-register', fn (Request $r) => Limit::perHour(5)->by($this->clientIp($r)));
        RateLimiter::for('auth-password', fn (Request $r) => Limit::perMinute(5)->by($this->clientIp($r)));
        RateLimiter::for('auth-verify', fn (Request $r) => Limit::perMinute(10)->by($r->user()->id));
        RateLimiter::for('auth-resend', fn (Request $r) => [Limit::perMinute(1)->by('minute:'.$r->user()->id), Limit::perHour(5)->by('hour:'.$r->user()->id)]);
    }

    private function clientIp(Request $request): string
    {
        return $request->attributes->get(VerifyFrontendClient::ATTRIBUTE, $request->ip());
    }
}
