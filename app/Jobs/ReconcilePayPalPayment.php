<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Bus\Dispatchable;

class ReconcilePayPalPayment
{
    use Dispatchable;

    public function __construct(public readonly int $paymentId) {}

    public function handle(PaymentService $service): void
    {
        $payment = Payment::find($this->paymentId);
        if ($payment && $payment->provider_order_id) {
            $service->reconcile($payment, captureApproved: true);
        }
    }
}
