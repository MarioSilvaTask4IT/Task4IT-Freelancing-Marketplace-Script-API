<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;

class ProfessionalTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'tags','user', 'reviewsToMe', 'reviews'
    ];

    protected $availableIncludes = [
        'projectsApplied','projectsWon','projectsFinished'
    ];

    public function transform(\Task4ItAPI\Professional $professional)
    {
        $data = [
            'id' => (int) $professional->id,
            'reviews_avg' => $professional->reviewAverage()
        ];

        return $data;
    }

    public function includeReviewsToMe(\Task4ItAPI\Professional $professional)
    {
        $reviews = $professional->reviewsToMe;

        return $this->collection($reviews, new \Task4ItAPI\Http\Transformers\ReviewTransformer);
    }

    public function includeReviews(\Task4ItAPI\Professional $professional)
    {
        $reviews = $professional->reviews;

        return $this->collection($reviews, new \Task4ItAPI\Http\Transformers\ReviewTransformer);
    }

    public function includeUser(\Task4ItAPI\Professional $professional)
    {
        $user = $professional->user;

        return $this->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
    }

    public function includeTags(\Task4ItAPI\Professional $professional)
    {
        $tags = $professional->tags;

        return $this->collection($tags, new \Task4ItAPI\Http\Transformers\TagTransformer);
    }

    public function includeProjectsApplied(\Task4ItAPI\Professional $professional)
    {
        $projects = $professional->projectsApplied;

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeProjectsWon(\Task4ItAPI\Professional $professional)
    {
        $projects = $professional->projectsWon;

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeProjectsFinished(\Task4ItAPI\Professional $professional)
    {
        $projects = $professional->projectsFinished;

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }
}
