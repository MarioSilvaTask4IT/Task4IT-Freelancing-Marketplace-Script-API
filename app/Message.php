<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['message', 'global'];

    public function users()
    {
        return $this->belongsToMany('\Task4ItAPI\User');
    }
}
