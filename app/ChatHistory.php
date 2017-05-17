<?php

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    public function user() {
    	return $this->belongsTo('\Task4ItAPI\User');
    }
}
