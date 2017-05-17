<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Task4ItAPI;

use Illuminate\Database\Eloquent\Model;
/**
 * Description of SubscriptionsPlan
 *
 */
class FreelancerSubscriptions extends Model {
    protected $table = 'freelancer_subscriptions';
    
    //public $timestamps = false;
    
    public function user()
    {
        return $this->belongsTo('\Task4ItAPI\User');
    }
}