<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Task4ItAPI\Http\Requests;
use Task4ItAPI\Http\Controllers\Controller;

class ChatController extends Controller
{

    /**
     * Send a message to a user
     * @param  Request $request   
     * @param  int  $projectId 
     * @return Response
     */
    public function chatToUser(Request $request, $projectId)
    {
        return $this->chat($request, $projectId, 'freelancer');
    }

    /**
     * Send a message to a freelancer
     * @param  Request $request   
     * @param  int  $projectId 
     * @return Response
     */
    public function chatToFreelancer(Request $request, $projectId)
    {
        return $this->chat($request, $projectId, 'user');
    }

    /**
     * Send a message on a project
     * @param  Request $request   
     * @param  int  $projectId 
     * @param  string  $chater    SEND FROM "USER" OR "FREELANCER"
     * @return Response
     */
    public function chat(Request $request, $projectId, $chater)
    {

        $project = \Task4ItAPI\Project::find($projectId);
        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        $user = app('Dingo\Api\Auth\Auth')->user();
        $executer = $project->executer()->first();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find project user');
        }

        if (!$executer) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find project executer');
        }
        //validate if auth user is project user
        // if ($chater == 'user') {
        //     $auth = app('Dingo\Api\Auth\Auth')->user();

        //     if ($auth->id != $user->id) {
        //         throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Only the owner of the project can chat to freelancer');
        //     }
        // } else {
        //     $freelancer = $user->professional;

        //     if ($freelancer->id != $executer->id) {
        //         throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('The executer of the project must be authenticated to chat to the user');
        //     }
        // }

        $validator = Validator::make($request->all(), array(
            'message' => 'required'
        ));

        if ($validator->fails()) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException(
                'Please enter the message.',
                $this->translateErrors($validator->errors()->all())
            );
        }

        try {
            $chatHistories = new \Task4ItAPI\ChatHistory;
            $chatHistories->project_id = $projectId;
            $chatHistories->user_id = $user->id;
            $chatHistories->message = $request->input('message');
            $chatHistories->save();
        } catch (Exception $e) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not save chat history.', $e->getMessage());
        }

        #push notification
        try {
            $notificationTag = sprintf('project-%s-notifications', $projectId);
            $data['userName'] = $user->first_name;
            $data['time'] = date('H:i A');
            $data['message'] = $request->input('message');
            $data['image'] = $user->image;
            $data['id'] = $user->id;
            \LPusher::trigger(
                    $notificationTag,
                    'project-new-chat-message',
                    ['message' => $data]);

            if ($chater == 'user') {
                $notificationTag = sprintf('freelancer-%s-notifications', $executer->id);
                $notificationMessage = sprintf('User %s has just sent you a message.', $project->user->displayName());
            } elseif ($chater == 'freelancer') {
                $notificationTag = sprintf('user-%s-notifications', $project->user->id);
                $notificationMessage = sprintf('Freelancer %s has just sent you a message.', $executer->user->displayName());
            }
            $notId = $chater == 'user' ? $user->id : $executer->user->id;

            $notification = [
                'tag' => sprintf('%s-chat', $chater),
                'message' => $notificationMessage,
                'ids' => [
                    'userId' => $notId,
                    'projectId' => $projectId
                ]
            ];
            $redisClient = app("PredisClient");
            $redisClient->lpush($notificationTag, json_encode($notification) );
            $redisClient->set(sprintf('%s.unread', $notificationTag), 1);

            #TODO: also send email
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }
    }
}
