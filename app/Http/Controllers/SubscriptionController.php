<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Http\Request;
use Cardinity\Client;
use Cardinity\Method\Payment;
use DB;
use Validator;

class SubscriptionController  extends Controller {
    
    /**
     * get payments plan to populate dropdown
     * @param Request $request
     */
    public function subscriptionsPlan(){
        $plans = \Task4ItAPI\PaymentPlans::all();
        return $this->response->collection($plans, new \Task4ItAPI\Http\Transformers\PaymentPlans);
    }
    
    public function paySubscription(Request $request){
        
        $response = array(
          "message" => "Unable to subscribe plan.",
          "status_code" => 500,
          "payment_id"  => ""
        );
        
        //validate user session
        $user = app('Dingo\Api\Auth\Auth')->user();
        
        if (!$user) {
            return $this->response->errorNotFound('No user logged in');
        }
        
        //validate input
        $validator = Validator::make($request->all(), array(
            'cc_number' => 'required|integer|digits_between:12,19',
            'cc_cvc' => 'required|digits_between:3,4',
            'cc_holdername' => 'required|string|between:2,30',
            'cc_amount' => 'required|integer|digits_between:1,5',
            'cc_expiry_month' => 'required|integer|digits_between:1,2|max:12',
            'cc_expiry_year' => 'required|integer|digits:4|min:'.date("Y"),
        ));

        if ($validator->fails()) {
            $errors = $this->translateErrors($validator->errors()->all());

            \Log::info("PAY_SUBSCRIPTION:: Input is not Valid");

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not process payment subscription.', $errors);
        }
        
        //everything went fine
        //lets get value by plan selected
        $planSelected = \Task4ItAPI\PaymentPlans::find($request->input('cc_amount'));
        if (!$planSelected) {
            return $this->response->errorNotFound('Could not find selected plan ' . $request->input('cc_amount'));
        }
        $amount = (float)$planSelected->value;
        $duration = $planSelected->duration;
        
        $todayDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime($todayDate." + {$duration} days"));
        
            //load cardinity configuration
            $consumerKey = env('CARDINITY_CONSUMER_KEY');
            $consumerSecret = env('CARDINITY_CONSUMER_SECRET');
            $cardinityCurrency = env('CARDINITY_CURRENCY');

            $cardinityClient = Client::create(array(
                'consumerKey' =>$consumerKey,
                'consumerSecret' => $consumerSecret
            ));

            $planName = $planSelected->name;

            $userFullName = $user->first_name;
            $userFullName .= empty($user->last_name)?"":" ".$user->last_name;
            $paymentDescription = $userFullName." subscribed plan ".$planName;
            $orderId = time().$user->id;
            $country = $user->origin;

            $method = new Payment\Create([
                'amount' => $amount,
                'currency' => $cardinityCurrency,
                'settle' => true,
                'description' => (string)$paymentDescription,
                'order_id' => $orderId,
                'country' => $country,
                'payment_method' => Payment\Create::CARD,
                'payment_instrument' => [
                    'pan' => (string)$request->input('cc_number'),
                    'exp_year' => (int)$request->input('cc_expiry_year'),
                    'exp_month' => (int)$request->input('cc_expiry_month'),
                    'cvc' => (string)$request->input('cc_cvc'),
                    'holder' => (string)$request->input('cc_holdername')
                ],
            ]);

            /** @type Cardinity\Method\Payment\Payment */
            $paymentId = "";
            try {
                $payment = $cardinityClient->call($method);
                $paymentId = $payment->getId();
            
                $pending = false;
                if($payment->isPending()){

                    $paymentId = "PENDING|".$expiryDate."|".$paymentId;

                    $expiryDate = "1952-01-01";
                    //3d secure
                    $authorizationInformation = $payment->getAuthorizationInformation();
                    $response['message'] = "You will be sent back to this site after you authorize the transaction.";
                    $response['status_code'] = 202;
                    $response['payment_id'] = $paymentId;
                    $response['PaReq'] = $authorizationInformation->getData();
                    $response['url'] = $authorizationInformation->getUrl();
                    $response['TermUrl'] = env('CARDINITY_CALLBACK_URL');
                    $response['planSelectedName'] = $planName;

                    $pending = true;
                }

                $this->updateFreelancerSubscription($user, $planSelected, $expiryDate,
                            $paymentId);

                //serializes object into string for storing in database
                //$serialized = serialize($payment);
                if(!empty($paymentId) && $pending === false){

                        $response['message'] = "Plan subscribed.";
                        $response['status_code'] = 200;
                        $response['payment_id'] = $paymentId;
                }
            } catch (\Cardinity\Exception\Declined $e) {
                $response['message'] = "Your payment details were declined.";
                $response['status_code'] = 422;
                $response['payment_id'] = $paymentId;
            }
            catch (\Cardinity\Exception\ValidationFailed $e){
                $response['message'] = $e->getMessage();
                $response['status_code'] = 500;
                $response['payment_id'] = $paymentId;
            }
            
                
            
            
        return $this->response->array($response);
    }
    
    public function finalizePaymentSubscription(Request $request){
     
        $response = array(
          "message" => "Unable to finalize payment of the subscription plan.",
          "status_code" => 500,
          "payment_id"  => ""
        );
        
        //validate user session
        $user = app('Dingo\Api\Auth\Auth')->user();
        
        if (!$user) {
            return $this->response->errorNotFound('No user logged in');
        }
        
    $validator = Validator::make($request->all(), array(
            'authorization' => 'required',
            'MD' => 'required',
            'PaRes' => 'required',
        ));

        if ($validator->fails()) {
            $errors = $this->translateErrors($validator->errors()->all());

            \Log::info("Finalize_PaymentSubscription:: Input is not Valid");

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not process payment finalize subscription.', $errors);
        }
    
        //find payment identifier
    $query = \Task4ItAPI\FreelancerSubscriptions::query();
        $freelancerSubs = $query->get()->where("payment_gateway_id", $request->input('MD'));    
    
    if($freelancerSubs->isEmpty()){
        $response = array(
                "message" => "Id is not valid.",
                "status_code" => 500
                 );
        \Log::info("Finalize_PaymentSubscription:: Id is not Valid");
        }
    else
    {
    
            $consumerKey = env('CARDINITY_CONSUMER_KEY');
            $consumerSecret = env('CARDINITY_CONSUMER_SECRET');
            //lets finalize the payment
            $cardinityClient = Client::create(array(
                    'consumerKey' =>$consumerKey,
                    'consumerSecret' => $consumerSecret
            ));
  
            list($pending,$expiryDate,$paymentId) = explode("|",$request->input('MD'));

            $method = new Payment\Finalize($paymentId, $request->input("PaRes"));
            
            try{
                $result = $cardinityClient->call($method);
                $freelancerSubscriptions = $freelancerSubs->first();
                $freelancerSubscriptions->expiry_date = $expiryDate;
                $freelancerSubscriptions->payment_gateway_id = $paymentId;

                if($freelancerSubscriptions->update()){
                        $response['message'] = "Plan subscribed.";
                        $response['status_code'] = 200;
                        $response['payment_id'] = $paymentId;
                }
                
            } catch (\Cardinity\Exception\Declined $e) {

                $response = array(
                    "message" => "3d-Secure Authorization failed.",
                    "status_code" => 500,
                    "payment_id"  => ""
                    );
            }
        }

        return $response;
    }
    
    public function updateFreelancerSubscription($user, $planSelected, $expiryDate, $paymentId, $fromUpateProfile = false){
        $query = \Task4ItAPI\FreelancerSubscriptions::query();
        $freelancerSubs = $query->get()->where("user_id", $user->id);
        
        $response = true;
        
        //check if exists
        if($freelancerSubs->isEmpty()){
            //freelancer subscriptions object
            $freelancerSubscriptions = new \Task4ItAPI\FreelancerSubscriptions();

            //
            //is just trial just update freelancer subscriptions table
            $freelancerSubscriptions->user_id = $user->id;
            $freelancerSubscriptions->payment_plans_id = $planSelected->id;
            $freelancerSubscriptions->expiry_date = $expiryDate;
            $freelancerSubscriptions->payment_gateway_id = $paymentId;
            //save subscriptions

            $response = $freelancerSubscriptions->save();
        }
        
        if(!$fromUpateProfile){
            $freelancerSubscriptions = $freelancerSubs->first();
            $freelancerSubscriptions->payment_plans_id = $planSelected->id;
            $freelancerSubscriptions->expiry_date = $expiryDate;
            $freelancerSubscriptions->payment_gateway_id = $paymentId;

            $response = $freelancerSubscriptions->update();
        }
        
        
        return $response;
    }
}
