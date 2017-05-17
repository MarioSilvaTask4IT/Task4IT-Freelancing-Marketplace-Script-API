<?php

namespace Task4ItAPI;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';

     /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo('\Task4ItAPI\User');
    }

    /**
     * Validates if the subscription is active
     * @return boolean
     */
    public function is_active()
    {
        $now = Carbon::now();

        return $this->next_capture_at < $now->timestamp ? 0 : 1;
    }

    public function scopeActive($query)
    {
        $now = Carbon::now();

        $query->whereNull('canceled_at')
            ->where('next_capture_at', '>=', $now->timestamp )
            ->orderBy('created_at', 'desc');
    }
}
