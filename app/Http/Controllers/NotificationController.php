<?php

namespace Task4ItAPI\Http\Controllers;

class NotificationController extends Controller
{

    public function userNotifications()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $notificationTag = sprintf('user-%s-notifications', $user->id);

        return $this->notifications($notificationTag);
    }

    public function freelancerNotifications()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $notificationTag = sprintf('freelancer-%s-notifications', $user->professional->id);

        return $this->notifications($notificationTag);
    }

    public function isUnreadUser()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $notificationTag = sprintf('user-%s-notifications.unread', $user->id);

        return $this->isUnread($notificationTag);
    }

    public function isUnreadFreelancer()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $notificationTag = sprintf('freelancer-%s-notifications.unread', $user->professional->id);

        return $this->isUnread($notificationTag);
    }

    protected function isUnread($notificationTag) {
        $client = app("PredisClient");
        
        $isUnread= $client->get($notificationTag);
        
        $toReturn = ['unread' => $isUnread ? 1 : 0 ];

        return $this->response->array(['data' => $toReturn ]);
    }

    protected function notifications($notificationTag)
    {
        $redisClient = app("PredisClient");
        $notifications = $redisClient->lrange($notificationTag, 0, -1 );
        $toRead = $redisClient->get(sprintf('%s.unread', $notificationTag));
        //$notifications = \Redis::lrange($notificationTag, 0, -1);
        //$toRead = \Redis::get(sprintf('%s.unread', $notificationTag));

        #mark messages as read
        if ($toRead) {
            $redisClient->set(sprintf('%s.unread', $notificationTag),     0);
            //\Redis::set(sprintf('%s.unread', $notificationTag),     0);
            
        }

        $simpleNotifications = array_map(function ($item) { return json_decode($item);}, $notifications);

        #limit to 5 notifications
        array_splice($simpleNotifications, 5);

        $response = [
            'notifications' => $simpleNotifications,
            'to_read' => $toRead ? 1 : 0,
        ];
        return $this->response->array(['data' => $response]);
    }
}
