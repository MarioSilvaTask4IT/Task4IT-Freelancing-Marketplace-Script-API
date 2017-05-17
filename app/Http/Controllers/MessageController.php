<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Http\Request;
use Validator;

class MessageController extends Controller
{

    /**
     * Sends a message to the administrator
     * @param  type Request $request
     * @return type
     */
    public function sendToAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), array(
            'message' => 'min:6|required',
            'subject' => 'min:3|required',
        ));

        if ($validator->fails()) {
            \Log::error("Not sending message");

            throw new \Dingo\Api\Exception\StoreResourceFailedException(
                'Could not send message.', $this->translateErrors($validator->errors()->all())
            );
        }

        $user = app('Dingo\Api\Auth\Auth')->user();

        #send email to system with the message
        $emailData = [
            'subtitle' => sprintf('New contact from task4it from user %s - %d (%s)', $user->first_name, $user->id, $user->email),
            'title' => $request->input('subject'),
            'body' => $request->input('message'),
        ];
        try {
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($user, $emailData) {
		$m->to(env('MAIL_TO_ADDRESS', 'user@user.com'), config('mail.to.name'))->subject($emailData['title']);
            }));
        } catch (Exception $e) {
		

            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));

            return;
        }

        $meta = ['message' => 'Message sent successfully.'];

        return $this->response->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer)->setMeta($meta);
    }

    /**
     * Send a message to a set of users
     * @param  type Request $request
     * @return type
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), array(
            'message' => 'min:6|required',
            'subject' => 'min:2|required'
        ));

        if ($validator->fails()) {
            \Log::error("Not sending message");

            throw new \Dingo\Api\Exception\StoreResourceFailedException(
                'Could not send message.', $this->translateErrors($validator->errors->all())
            );
        }

        if ($request->input('ids')) {
            $ids = explode(',', $request->input('ids'));
            $users = \Task4ItAPI\User::whereIn('id', $ids)->get();
        } else {
            $users = \Task4ItAPI\User::get();
        }

        if (!count($users)) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('The ids received do not match valid users. Message will not be sent!');
        }

        $message = new \Task4ItAPI\Message();
        $message->message = $request->input('message');

        $subject = $request->input('subject', null);
        if ($subject) {
            $message->subject = $subject;
        }

        $message->save();

        //send the same message to a set of users
        foreach ($users as $user) {
            $user->messages()->save($message);

            if (!$user->email) {
                continue;
            }

            #also send mail to the user with the message
            $emailData = [
                'subtitle' => 'You\'ve just received a new message!',
                'title' => $message->subject ? $message->subject : 'New message',
                'body' => $message->message,
            ];
            try {
                \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($user, $emailData) {
                    $m->to($user->email, $user->first_name)->subject($emailData['title']);
                }));
            } catch (Exception $e) {
               \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
            }
        }

        $meta = ['message' => 'Message sent successfully.'];

        return $this->response->item($message, new \Task4ItAPI\Http\Transformers\MessageTransformer)->setMeta($meta);
    }
}
