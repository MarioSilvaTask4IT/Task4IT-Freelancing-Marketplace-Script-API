<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class PaymentTransformer extends TransformerAbstract
{
    public function transform(\Task4ItAPI\Payment $payment)
    {
        $data = [
            'status' => (string) $payment->status,
            'period' => (string) $payment->period,
            'created_at' => (string) $payment->created_at,
        ];

        return $data;
    }
}
