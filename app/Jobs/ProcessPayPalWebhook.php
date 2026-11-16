<?php

namespace App\Jobs;

use App\Services\PayPalWebhookService;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPayPalWebhook
{
    use Dispatchable;

    public function __construct(public readonly int $webhookId) {}

    public function handle(PayPalWebhookService $service): void
    {
        $service->process($this->webhookId);
    }
}
