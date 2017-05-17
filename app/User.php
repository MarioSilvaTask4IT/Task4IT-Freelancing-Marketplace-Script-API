<?php

namespace Task4ItAPI;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, SoftDeletes, MonthlyCountable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    protected $dates = ['deleted_at'];

    public function displayName()
    {
        $name = $this->first_name;

        if ($this->last_name) {
            $name .= ' ' . $this->last_name;
        }

        return $name;
    }

    public function professional()
    {
        return $this->belongsTo('\Task4ItAPI\Professional');
    }

    public function projects()
    {
        return $this->hasMany('\Task4ItAPI\Project');
    }

    public function projectsCompleted()
    {
        return $this->projects()->where('status', '=', \Task4ItAPI\Project::COMPLETED)->orderBy('created_at', 'desc');
    }

    public function projectsActive()
    {
        return $this->projects()->where('status', '=', \Task4ItAPI\Project::EXECUTING)->orderBy('created_at', 'desc');
    }

    public function projectsPending()
    {
        return $this->projects()->where('status', '=', \Task4ItAPI\Project::PUBLISHED)->orderBy('created_at', 'desc');
    }

    public function payments()
    {
        return $this->hasMany('\Task4ItAPI\Payment');
    }

    public function lastPayment()
    {
        return $this->payments()->where('status', '=', 'SUCCESS')->orderBy('created_at', 'desc')->first();
    }

    public function projectsTotals()
    {
        $projects = $this->projects()->select(\DB::raw('count(1) as count'), 'status')
                ->groupBy('status')
                ->get();

        $pending = $this->projectsPending ? $this->projectsPending()->count() : 0;
        $active = $this->projectsActive ? $this->projectsActive()->count() : 0;
        $completed = $this->projectsCompleted ? $this->projectsCompleted()->count() : 0;

        return [
            'active' => $active,
            'pending' => $pending,
            'completed' => $completed,
            'total' => $active + $pending + $completed,
        ];

        return $projects;
    }

    public function oauthIdentities()
    {
        return $this->hasOne('\Task4ItAPI\OauthIdentities');
    }

    public function scopeActive($query)
    {
        $query->where('active', '=', 1);
    }

    public function reviewsToMe()
    {
        return $this->belongsToMany('\Task4ItAPI\Professional', 'user_reviews', 'user_id', 'professional_id')
                    ->withPivot('project_id', 'review', 'stars')->withTimestamps();
    }

    public function reviews()
    {
        return $this->belongsToMany('\Task4ItAPI\Professional', 'freelancer_reviews', 'user_id', 'professional_id')
                    ->withPivot('project_id', 'review', 'stars')->withTimestamps();
    }

    public function messages()
    {
        return $this->belongsToMany('\Task4ItAPI\Message');
    }

    public function userReports()
    {
        return $this->belongsToMany('\Task4ItAPI\User', 'user_reports', 'user_id', 'reported_id')
                    ->withPivot('reason')->withTimestamps();
    }

    public function reportedBy()
    {
        return $this->belongsToMany('\Task4ItAPI\User', 'user_reports', 'reported_id', 'user_id')
                    ->withPivot('reason')->withTimestamps();
    }

    public function projectReports()
    {
        return $this->belongsToMany('\Task4ItAPI\Project', 'project_reports', 'user_id', 'reported_id')
                    ->withPivot('reason')->withTimestamps();
    }

    public function offer()
    {
        return $this->belongsTo('\Task4ItAPI\Offer');
    }

    public function subscriptions()
    {
        return $this->hasMany('\Task4ItAPI\Subscription');
    }
    
    public function freelancerSubscriptions()
    {
        return $this->hasOne('\Task4ItAPI\FreelancerSubscriptions');
    }

    public function activeSubscription()
    {
       return $this->subscriptions()->active()->first();

        // return $this->subscriptions()
        //     ->whereNull('canceled_at')
        //     ->where('next_capture_at', '>=', $now->timestamp )
        //     ->orderBy('created_at', 'desc')
        //     ->first();
    }

    /**
     * Finds and returns average of reviews made to this user
     * @return type
     */
    public function reviewAverage()
    {
        $reviews = $this->reviewsToMe;

        if (!count($reviews)) {
            return 0;
        }

        $sum = 0;

        foreach ($reviews as $review) {
            $sum += (int) $review->pivot->stars;
        }

        $avg = $sum / count($reviews);

        return $avg;
    }

    /**
     * The number of projects created per month for a particular year
     * @param  type $year
     * @return an   array with ['0' => counter, 1 => counter, ....]
     */
    public function projectCounters($year)
    {
        \DB::connection()->enableQueryLog();
        $projects = $this->projects()->select(\DB::raw('count(1) as count'), \DB::raw('MONTHNAME(created_at) as month'))
                ->groupBy('month')
                ->where(\DB::raw('YEAR(created_at)'),  '=', $year)
                ->orderBy('month')
                ->get();
        // $query = \DB::getQueryLog();
        // dd('query', $query);
        //
        return $this->monthlyCount($year, $projects);
    }
}
