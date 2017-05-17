<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class ProjectTransformer extends TransformerAbstract
{

    protected $availableIncludes = [
        'candidates', 'executer'
    ];

    protected $defaultIncludes = [
        'tag','user', 'chatHistories'
    ];

    public function transform(\Task4ItAPI\Project $project)
    {
        $data = [
            'id' => (int) $project->id,
            'title' => (string) $project->title,
            'description' => (string) $project->description,
            'code' => (string) $project->code,
            'image' => (string) $project->image,
            'status' => (string) $project->status,
            'price' => (float) $project->price,
            'level' => (string) $project->level,
            'date' => (string) $project->created_at,
        ];

            // dd($project->executer->first()->pivot->start_date);

        if ($project->executer->first()) {
            $data['start_date'] = (string) $project->executer->first()->pivot->start_date;
            $data['end_date'] = (string) $project->executer->first()->pivot->end_date;
            $data['payment_confirmed'] = (int) $project->executer->first()->pivot->payment_confirmed;
            $data['payment_confirmation_date'] = (string) $project->executer->first()->pivot->payment_confirmation_date;
        }

        return $data;
    }

    public function includeCandidates(\Task4ItAPI\Project $project)
    {
        $professionals = $project->candidates;

        #order candidates by review average
        $sortFunction = function ($a, $b) {
            if ($a->reviewAverage() == $b->reviewAverage()) {
                return 0;
            }

            return ($a->reviewAverage() < $b->reviewAverage()) ? 1 : -1;
        };

        $professionals = $professionals->sort($sortFunction);

        return $this->collection($professionals, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer);
    }

    public function includeTag(\Task4ItAPI\Project $project)
    {
        $tag = $project->tag;

        if (!$tag) {
            return;
        }

        return $this->item($tag, new \Task4ItAPI\Http\Transformers\TagTransformer);
    }

    public function includeChatHistories(\Task4ItAPI\Project $project)
    {
        $chatHistories = $project->chatHistories;

        if (!$chatHistories) {
            return;
        }

        return $this->collection($chatHistories, new \Task4ItAPI\Http\Transformers\ChatHistoryTransformer);
    }

    public function includeUser(\Task4ItAPI\Project $project)
    {
        $user = $project->user;

        if (!$user) {
            return;
        }

        return $this->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
    }

    public function includeExecuter(\Task4ItAPI\Project $project)
    {
        $professional = $project->executer;

        return $this->collection($professional, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer);
    }
}
