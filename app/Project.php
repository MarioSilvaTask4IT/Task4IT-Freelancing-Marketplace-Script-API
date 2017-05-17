<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $table = 'projects';
    public $timestamps = true;

    const DRAFT = "draft";
    const PUBLISHED = "published";
    const EXECUTING = "executing";
    const COMPLETED = "completed";

    public function user()
    {
        return $this->belongsTo('\Task4ItAPI\User');
    }

    public function tag()
    {
        return $this->belongsTo('\Task4ItAPI\Tag');
    }

    public function candidates()
    {
        return $this->belongsToMany('\Task4ItAPI\Professional');
    }

    public function executer()
    {
        return $this->belongsToMany('\Task4ItAPI\Professional')
                    ->withPivot('accepted', 'finished', 'start_date', 'end_date', 'payment_confirmed', 'payment_confirmation_date')
                    ->where('accepted', '=', 1);
    }

    public function reportedBy()
    {
        return $this->belongsToMany('\Task4ItAPI\User', 'project_reports', 'reported_id', 'user_id')
                    ->withPivot('reason')->withTimestamps();
    }

    public function chatHistories()
    {
        return $this->hasMany('\Task4ItAPI\ChatHistory');
    }

}
