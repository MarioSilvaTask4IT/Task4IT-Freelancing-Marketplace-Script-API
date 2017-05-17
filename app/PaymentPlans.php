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
class PaymentPlans extends Model {
    protected $table = 'payment_plans';
    
    public $timestamps = false;
}