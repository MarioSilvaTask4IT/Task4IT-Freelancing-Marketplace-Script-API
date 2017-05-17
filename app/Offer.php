<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $table = 'offers';

    public $timestamps = false;

    public function users()
    {
        return $this->hasMany('\Task4ItAPI\User');
    }
}
