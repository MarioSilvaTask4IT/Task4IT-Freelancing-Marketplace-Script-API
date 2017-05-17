<?php

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;

class UserTransformer extends TransformerAbstract
{
    protected $defaultIncludes = ['offer', 'activeSubscription'];

    protected $availableIncludes = [
        'projects','professional', 'openProjects', 'finishedProjects',
        'reviewsToMe', 'reviews', 'payments', 'offer', 'subscriptions','activeSubscription'
    ];

    public function transform(\Task4ItAPI\User $user)
    {
        //get plan name
        
        if(isset($user->freelancerSubscriptions)){

            $query = \Task4ItAPI\PaymentPlans::query();
            $plan = $query->get()->where("id", $user->freelancerSubscriptions->payment_plans_id)->first();
            $user->freelancerSubscriptions->name = $plan->name;
        }
        
        
        $data = [
            'id' => (int) $user->id,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'origin' => (string) $user->origin,
            'active' => (int) $user->active,
            'wallet' => (int) $user->wallet,
            'image' => (string) $user->image,
            'address' => (string) $user->address,
            'occupation' => (string) $user->occupation,
            'about_me' => (string) $user->about_me,
            'NIF' => (string) $user->NIF,
            'reviews_avg' => $user->reviewAverage(),
            'is_professional' => $user->professional ? 1 : 0,
            'is_admin' => (int) $user->is_admin,
            'paymill_id' => (string) $user->paymill_id,
            'level'   => (int) $user->level,
        ];
        if(isset($user->freelancerSubscriptions)){
            $data['freelancerSubscriptions'] = $user->freelancerSubscriptions;
        }
        

        if ($this->canAccessPrivateData($user)) {
            $data['email'] = (string) $user->email;
            $data['phone'] = (string) $user->phone;
            $data['can_change_country'] = (int) $user->change_country;
            $data['receive_payment_info'] = (string) $user->receive_payment_info;
            $data['linkedin'] = (string) $user->linkedin;
            $data['behance'] = (string) $user->behance;
            $data['github'] = (string) $user->github;
            $data['skype'] = (string) $user->skype;
            $data['dribbble'] = (string) $user->dribbble;
            $data['youtube'] = (string) $user->youtube;
            $data['personal_website'] = (string) $user->personal_website;
        }

        return $data;
    }

    /**
     * Check if it can access private data
     *    A logged user can access the private data if he is looking at himself,
     * if he is executing a project owner by the $user, or if the user is developing a project he
     * owned.
     * @param  \Task4ItAPI\User $user
     * @return boolean
     */
    protected function canAccessPrivateData(\Task4ItAPI\User $user)
    {
        $logged = app('Dingo\Api\Auth\Auth')->user();

        if (!$logged) {
            return false;
        }

        #user looking at itself
        if ($logged && $logged->id == $user->id) {
           return true;
        }

        #if the logged in freelancer is doing a job with the $user, open
        #also open if the logged in user is doing a job with the user freelancer
        if ($logged->professional) {
            #check if the logged is executing a project owned by the $user
            $projects = $user->projects()->where('status', '=', \Task4ItAPI\Project::EXECUTING)->get();

            foreach ($projects as $project) {
                $executer = $project->executer->first();
                if ($executer && $executer->id == $logged->professional_id) {
                    return true;
                }
            }
        }

        if ($user->professional) {
            #check if the $user is executing a project owned by the $logged
            $projects = $logged->projects()->where('status', '=', \Task4ItAPI\Project::EXECUTING)->get();

            foreach ($projects as $project) {
                $executer = $project->executer->first();

                if ($executer && $executer->id == $user->professional_id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function includeReviewsToMe(\Task4ItAPI\User $user)
    {
        $reviews= $user->reviewsToMe;

        return $this->collection($reviews, new \Task4ItAPI\Http\Transformers\ReviewTransformer);
    }

    public function includeReviews(\Task4ItAPI\User $user)
    {
        $reviews= $user->reviews;

        return $this->collection($reviews, new \Task4ItAPI\Http\Transformers\ReviewTransformer);
    }

    public function includeProjects(\Task4ItAPI\User $user)
    {
        $projects = $user->projects;

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeOpenProjects(\Task4ItAPI\User $user)
    {
        $projects = $user->projects->where('status', '=', \Task4ItAPI\Project::EXECUTING);

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeFinishedProjects(\Task4ItAPI\User $user)
    {
        $projects = $user->projects->where('status', '=', \Task4ItAPI\Project::COMPLETED);

        return $this->collection($projects, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function includeProfessional(\Task4ItAPI\User $user)
    {
        $professional = $user->professional;

        if (!$professional) {
            return;
        }

        return $this->item($professional, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer);
    }

    public function includeOffer(\Task4ItAPI\User $user)
    {
        $offer= $user->offer;

        if (!$offer) {
            return;
        }

        return $this->item($offer, new \Task4ItAPI\Http\Transformers\OfferTransformer);
    }

    public function includeSubscriptions(\Task4ItAPI\User $user)
    {
        $subscriptions = $user->subscriptions;

        return $this->collection($subscriptions, new \Task4ItAPI\Http\Transformers\SubscriptionTransformer);
    }

    public function includeActiveSubscription(\Task4ItAPI\User $user)
    {
        $subscription = $user->activeSubscription();

        if (!$subscription) {
            return;
        }

        return $this->item($subscription, new \Task4ItAPI\Http\Transformers\SubscriptionTransformer);
    }

}
