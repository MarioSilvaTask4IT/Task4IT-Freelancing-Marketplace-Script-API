<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    use SoftDeletes, MonthlyCountable;

    protected $table = 'professionals';
    public $timestamps = true;

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->hasOne('\Task4ItAPI\User');
    }

    public function tags()
    {
        return $this->belongsToMany('\Task4ItAPI\Tag');
    }

    public function projectsApplied()
    {
        return $this->belongsToMany('\Task4ItAPI\Project');
    }

    public function projectsWon()
    {
        return $this->belongsToMany('\Task4ItAPI\Project')->withPivot('accepted', 'finished')->where('accepted', '=', 1)->where('finished', '=', 0);
    }

    public function projectsFinished()
    {
        return $this->belongsToMany('\Task4ItAPI\Project')->withPivot('finished')->where('finished', '=', 1);
    }

    public function projectsTotals()
    {
        $pending = $this->projectsApplied ? $this->projectsApplied()->count() : 0;
        $active = $this->projectsWon ? $this->projectsWon()->count() : 0;
        $completed = $this->projectsFinished ? $this->projectsFinished()->count() : 0;

        return [
            'active' => $active,
            'pending' => $pending,
            'completed' => $completed,
            'total' => $active + $pending + $completed,
        ];
    }

    public function projectsFinishedCounters($year)
    {
        $projects = $this->projectsFinished()->select(\DB::raw('count(1) as count'), \DB::raw('MONTHNAME(end_date) as month'))
                ->groupBy('month')
                ->where(\DB::raw('YEAR(end_date)'),  '=', $year)
                ->orderBy('month')
                ->get();

        return $this->monthlyCount($year, $projects);
    }

    public function projectsPending()
    {
        return $this->belongsToMany('\Task4ItAPI\Project')->withPivot('accepted')->where('status', '!=', \Task4ItAPI\Project::EXECUTING)->where('accepted', '=', 0);
    }

    public function reviews()
    {
        return $this->belongsToMany('\Task4ItAPI\User', 'user_reviews', 'professional_id', 'user_id')
            ->withPivot('project_id', 'review', 'stars')->withTimestamps();
    }

    public function reviewsToMe()
    {
        return $this->belongsToMany('\Task4ItAPI\User', 'freelancer_reviews', 'professional_id', 'user_id')
            ->withPivot('project_id', 'review', 'stars')->withTimestamps();
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

    public function setTags($tags)
    {
        $tagList = explode(',', $tags);

        //trim tags
        $tagList = array_map(function ($value) { return trim($value);}, $tagList);

        $validTags = \Task4ItAPI\Tag::whereIn('tag', $tagList)->get();

        if (!$validTags) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('No valid tags received');
        }

        //gets the tag ids
        $ids = array_map(function ($tag) { return $tag['id']; }, $validTags->toArray());

        //updates freelancer tags, so that it will have just the ones selected here
        try {
            $this->tags()->sync($ids);
        } catch (Exception $e) {
            \Log::error('Could not save tags to professional because: ' . $e->getMessage());
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Error while saving professional ' . $professionalId . ' tags.');
        }
    }

}
