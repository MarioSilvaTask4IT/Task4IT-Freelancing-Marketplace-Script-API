<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Task4ItAPI\Http\Transformers;

use League\Fractal;
use League\Fractal\TransformerAbstract;

/**
 * Description of PaymentsPlan
 *
 * @author Mario
 */
class PaymentPlans extends TransformerAbstract {
    
    public function transform(\Task4ItAPI\PaymentPlans $paymentPlans)
    {
        //validate user session
        $user = app('Dingo\Api\Auth\Auth')->user();
        

        //return only visible ones
        if($paymentPlans->visible && @$user->level >= $paymentPlans->level){
            
            $data = [
                'id' => (int) $paymentPlans->id,
                'duration' => (string) $paymentPlans->duration,
                'name' => (string) $paymentPlans->name,
                'price' => (string) $paymentPlans->value,
            ];

            return $data;
        } else {
            return ['id' => 0, 'duration' >= "Lifetime", "name" => "Free plan", "price" => 0];
        }
        
    }
}
