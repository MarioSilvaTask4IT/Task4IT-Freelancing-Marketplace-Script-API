<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class OfferTransformer extends TransformerAbstract
{
    public function transform(\Task4ItAPI\Offer $offer)
    {
        $data = [
            'id' => (int) $offer->id,
            'name' => (string) $offer->name,
            'amount' => (string) $offer->amount,
            'paymill_id' => (string) $offer->paymill_id,
        ];

        return $data;
    }
}
