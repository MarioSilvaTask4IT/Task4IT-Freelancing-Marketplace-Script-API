<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class ChatHistoryTransformer extends TransformerAbstract
{

	protected $defaultIncludes = [
        'user'
    ];

    public function transform(\Task4ItAPI\ChatHistory $chatHistory)
    {
        $data = [
            'message' => $chatHistory->message,
            'created_at' => $chatHistory->created_at,
            'updated_at' => $chatHistory->updated_at
        ];

        return $data;
    }

    public function includeUser(\Task4ItAPI\ChatHistory $chatHistory)
    {
        $user = $chatHistory->user;

        if (!$user) {
            return;
        }

        return $this->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
    }
}
