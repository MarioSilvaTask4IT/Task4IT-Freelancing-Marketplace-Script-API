<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class MessageTransformer extends TransformerAbstract
{
    public function transform(\Task4ItAPI\Message $message)
    {
        $data = [
            'subject' => (string) $message->subject,
            'message' => (string) $message->message,
            'created_at' => (string) $message->created_at,
        ];

        return $data;
    }
}
