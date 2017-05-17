<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';

    public $timestamps = false;

    public function projects()
    {
        return $this->hasMany('\Task4ItAPI\Project');
    }

    public function professional()
    {
        return $this->belongsToMany('\Task4ItAPI\Professional');
    }

}
