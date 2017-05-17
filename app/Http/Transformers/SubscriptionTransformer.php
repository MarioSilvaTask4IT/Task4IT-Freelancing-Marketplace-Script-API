<?php

namespace Task4ItAPI\Http\Transformers;

use Carbon\Carbon;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class SubscriptionTransformer extends TransformerAbstract
{
    public function transform(\Task4ItAPI\Subscription $subscription)
    {
        $next_capture_at = is_null($subscription->next_capture_at) ? null : Carbon::createFromTimestamp($subscription->next_capture_at);
        $canceled_at = is_null($subscription->canceled_at) ? null : Carbon::createFromTimestamp($subscription->canceled_at);

        $data = [
            'id' => (int) $subscription->id,
            'active' => (boolean) $subscription->active,
            'paymill_id' => (string) $subscription->paymill_id,
            'payment_id' => (string) $subscription->payment_id,
            'next_capture_at' => (string) $next_capture_at,
            'created_at' => (string) $subscription->created_at,
            'canceled_at' => (string) $canceled_at,
            'is_active' => (boolean) $subscription->is_active(),
        ];

        return $data;
    }
}
