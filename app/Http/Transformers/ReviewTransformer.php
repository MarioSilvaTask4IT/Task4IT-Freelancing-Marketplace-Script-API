<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

class ReviewTransformer extends TransformerAbstract
{

    protected $availableIncludes = [
        'project','professional','user'
    ];

    public function transform(\Illuminate\Database\Eloquent\Model $user)
    {
        $data = [
            'title' => (string) $user->pivot->title,
            'review' => (string) $user->pivot->review,
            'stars' => (int) $user->pivot->stars,
            'project_id' => (int) $user->pivot->project_id,
            'user_id' => (int) $user->pivot->user_id,
            'professional_id' => (int) $user->pivot->professional_id,
            'created_at' => (string) $user->pivot->created_at,
        ];

        return $data;
    }

    public function includeProject(\Illuminate\Database\Eloquent\Model $user)
    {
        $project = \Task4ItAPI\Project::find($user->pivot->project_id);

        return $this->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeProfessional(\Illuminate\Database\Eloquent\Model $user)
    {
        $professional = \Task4ItAPI\Professional::find($user->pivot->professional_id);

        if (!$professional) {
             return;
        }

        return $this->item($professional, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer);
    }

    public function includeUser(\Illuminate\Database\Eloquent\Model $user)
    {
        $user = \Task4ItAPI\User::find($user->pivot->user_id);

        if (!$user) {
             return;
        }

        return $this->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
    }

}
