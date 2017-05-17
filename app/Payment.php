<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['status', 'extra'];

    public function user()
    {
        return $this->belongsTo('\Task4ItAPI\User');
    }
}
