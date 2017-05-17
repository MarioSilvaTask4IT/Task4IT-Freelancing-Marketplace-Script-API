<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class TagTransformer extends TransformerAbstract
{
    public function transform(\Task4ItAPI\Tag $tag)
    {
        $data = [
            'tag' => (string) $tag->tag,
            'description' => (string) $tag->description,
        ];

        return $data;
    }
}
