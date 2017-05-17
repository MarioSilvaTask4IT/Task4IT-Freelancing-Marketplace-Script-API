<?php

namespace Task4ItAPI\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Task4ItAPI\Payment;

class PaymentController extends Controller
{
    
    /**
     *
     * @return type
     */
    public function subscriptionWebHook(Request $request)
    {
        $event = $request->input('event');
        \Log::info("event in variable: ", $event);
        $type = $event['event_type'];
        \Log::info("type: $type");
        $resources = $event['event_resource'];

        $subscription = \Task4ItAPI\Subscription::where('paymill_id', '=', $resources['subscription']['id'])->first();

        #error if no subscription
        if (!$subscription) {
            \Log::error('No subscription found in webhook for paymill id' . $resources['subscription']['id'] );

            return $this->response->noContent();
        }

        #error if no user and offer
        $user = $subscription->user;
        if (!$user) {
            \Log::error('No user for subscription ' . $subscription->id );

            return $this->response->noContent();
        }

        $offer = $user->offer;
        if (!$offer) {
            \Log::error('No offer for user ' . $user->id );

            return $this->response->noContent();
        }

        #update next captures at of the subscription
        if ($type == 'subscription.succeeded') {
            $subscription->next_capture_at = $resources['subscription']['next_capture_at'];
            $subscription->active = 1;
            $subscription->save();

            \Log::info('Subscription next capture updated to ' . $subscription->next_capture_at);

            #send email to user letting him know the subscription was renewed
            $emailData = [
                'subtitle' => $offer->name . ' subscription renewed, thank you!',
                'title' => 'Subscription renewed',
                'body' => sprintf('Thank you very much for renewing your %s subscription. You can use the service without limits.', $offer->name),
            ];
        } elseif ($type == 'subscription.failed') {
            $subscription->active = 0;
            $subscription->save();

            \Log::info('Subscription set as inactive');

            #send email to user letting him know there was a problem with the payment.
            $emailData = [
                'subtitle' => $offer->name . ' subscription renewal problems!',
                'title' => 'Subscription renewal failed',
                'body' => sprintf('%s subscription renewal problems, can you please update your payment method or contact us?', $offer->name),
            ];

        } elseif ($type == 'subscription.canceled') {
            $subscription->active = 0;
            $subscription->canceled_at = $resources['subscription']['canceled_at'];
            $subscription->save();

            \Log::info('Subscription canceled');

            #send email to user saying goodbye.
            $emailData = [
                'subtitle' => $offer->name . ' subscription canceled!',
                'title' => 'Subscription canceled',
                'body' => sprintf('It is with sad eyes that we see you go away. Can you let us know what went wrong with your usage of task4it? We hope to see you back soon.'),
            ];
        } elseif ($type == 'subscription.expiring') {
            #TODO: send email to the user letting him know the subscription is about to expire.

            \Log::info('Subscription about to expire');

            #send email to user letting him know.
            $emailData = [
                'subtitle' => $offer->name . ' subscription is about to expire!',
                'title' => 'Subscription about to expire',
                'body' => sprintf('Your subscription is about to expire, please make sure you have your payment method updated.'),
            ];
        }

        $this->sendEmail($user, $emailData);

        return $this->response->noContent();
    }

    protected function sendEmail($user, $emailData)
    {
        \Log::info('Queue send email to ' . $user->email);
        try {
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($user, $emailData) {
                $m->to($user->email, $user->first_name)->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
        }
    }

    public function doPayment(Request $request)
    {
        #TODO: if user has active subscription, abort this! or do an update to the subscription instead!!

        if (!$request->input('paymillToken')) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Must receive paymill token to do the payment!!');
        }
        if (!$request->input('offerId')) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Must receive offerId to do the payment!!');
        }

        $offer = \Task4ItAPI\Offer::find($request->input('offerId'));
        if (!$offer) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find offer received!');
        }
        // \Log::error("Offer paymill_id", $offer->paymill_id);

        $user = app('Dingo\Api\Auth\Auth')->user();

        #create user on paymill
        if (!$user->paymill_id) {
            $client = \Paymill::Client()
                ->setEmail($user->email)
                ->create();

            #save paymill id on user table
            $user->paymill_id = $client->getId();
            $user->save();
        }

        #create credit card on paymill
        #TODO: use this if no credit card already there
        #check if payment already exists
        $payment = \Paymill::Payment()
            ->setToken($request->input('paymillToken'))
            ->setClient($user->paymill_id)
            ->create();

        #make subscription with the credit card created
        $pm_subscription = \Paymill::Subscription()
            ->setOffer($offer->paymill_id)
            ->setPayment($payment->getId())
            ->create();

        #assuming everything went fine
        $user->offer_id = $offer->id;
        $user->save();

        $subscription = new \Task4ItAPI\Subscription();
        $subscription->user_id = $user->id;
        $subscription->paymill_id = $pm_subscription->getId();
        $subscription->payment_id = $payment->getId();
        $subscription->next_capture_at = $pm_subscription->getNextCaptureAt();
        $subscription->active = 1;
        $subscription->save();

        $emailData = [
            'subtitle' => $offer->name . ' subscription made, thank you!',
            'title' => 'Subscription successfull',
            'body' => sprintf('Welcome to task4it. Thank you very much for subscribing. You can use the service without limits.'),
        ];

        try {
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($user, $emailData) {
                $m->to($user->email, $user->first_name)->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
        }

        return $this->response->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
    }
}
