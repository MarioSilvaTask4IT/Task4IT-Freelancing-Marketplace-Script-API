<?php

namespace Task4ItAPI\Http\Controllers;

use Carbon\Carbon as Carbon;
use Illuminate\Http\Request;
use Validator;
use DB;

class ProjectController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return Response
    */
    public function index(Request $request)
    {
       $projects = \Task4ItAPI\Project::with('tag', 'user')->paginate($request->input("per_page", config("pagination.per_page")));

       return $this->response->paginator(
           $projects,
           new \Task4ItAPI\Http\Transformers\ProjectTransformer
       );
    }

    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return Response
    */
    public function show($id)
    {
        $project = \Task4ItAPI\Project::with('tag')->find($id);
        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer);
    }

    public function update(Request $request, $projectId)
    {

        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            return $this->response->errorNotFound('No user logged in');
        }

        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if ($project->user->id != $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('You can only edit your own projects!');
        }

        if ($project->status != \Task4ItAPI\Project::PUBLISHED) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('You can only edit published projects!');
        }

        $validator = Validator::make($request->all(), array(
            'title' => 'min:2',
            'code'  => 'min:6',
            'tag'   => 'exists:tags,tag',
            'price' => 'numeric',
            'level' => 'numeric'
        ));

        if ($validator->fails()) {
            $errors = $this->translateErrors($validator->errors()->all());

            \Log::error("NOT validating project");

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not add project.', $errors);
        }

        $code = $request->input("code", null);
        if ($code) {
            $project->code = $request->input("code");
        }

        $title = $request->input("title", null);
        if ($title) {
            $project->title = $request->input("title");
        }

        $description = $request->input("description", null);
        if (!is_null($description)) {
            $project->description = $description;
        }

        $image = $request->input("image", null);
        if (!is_null($image)) {
            $project->image = $image;
        }

        $price = $request->input("price", null);
        if (!is_null($price)) {
            $project->price = $price;
        }

        $level = $request->input("level", null);
        if (!is_null($level)) {
            $project->level = $level;
        }

        $project->save();

        if ($request->input('tag')) {
            $tag = \Task4ItAPI\Tag::where('tag', '=', $request->input('tag'))->first();
            //add relationships
            $tag->projects()->save($project);
        }

        $meta = ['message' => 'Project updated successfully'];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    /**
        * Remove the specified resource from storage.
        *
        * @param  int  $id
        * @return Response
        */
    public function destroy($projectId)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if ($project->user->id != $user->id or !$user->is_admin) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('You can only close your own projects!');
        }

        $executer = $project->executer()->first();

        try {
            $project->delete();
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));

            throw new \Dingo\Api\Exception\DeleteResourceFailedException('Error while deleting project!');
        }

        #send an email and push notification to the affected freelancer
        if ($executer) {

            $notificationTag = sprintf('freelancer-%s-notifications', $executer->id);
            $notificationMessage = sprintf('The project %s has been deleted', $project->title);
            #push notification
            try {
                \LPusher::trigger(
                    $notificationTag,
                    'project-deleted',
                    ['message' => $notificationMessage]);

            } catch (Exception $e) {
                \Log::error("Could not send push notifications because ", $e->getMessage());
            }

            try {
                $notification = [
                    'tag' => 'project-deleted',
                    'message' => $notificationMessage,
                    'ids' => [
                        'projectId' => $projectId
                    ]
                ];
                $redisClient = app("PredisClient");
                $redisClient->lpush($notificationTag, json_encode($notification) );
                $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
                //\Redis::lpush($notificationTag, json_encode($notification) );
                //\Redis::set(sprintf('%s.unread', $notificationTag), 1);

            } catch (Exception $e) {
                \Log::error("Could not send push notifications because ", $e->getMessage());
            }

            #email
            $emailData = [
                'subtitle' => 'A project you\'ve been working on has been deleted!',
                'title' => 'Project deleted',
                'body' => sprintf('The project %s has been deleted.', $projectId),
            ];

            try {
                \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($executer, $emailData) {
                    $m->to($executer->user->email, $executer->user->first_name)->subject($emailData['title']);
                }));
            } catch (Exception $e) {
                \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
            }
        }

        return $this->response->noContent();
    }

    public function close($projectId)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if ($project->user->id != $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('You can only close your own projects!');
        }

        if ($project->status != \Task4ItAPI\Project::EXECUTING) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Could not close project which is not in ' . \Task4ItAPI\Project::EXECUTING . ' stage ');
        }

        if (!$project->executer()->count()) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot finish a project which has no executer');
        }

        \Task4ItAPI\ProfessionalProject::where('accepted', '=', 1)
                    ->where('project_id', '=', $projectId)->update(array('finished' => 1, 'end_date' => Carbon::now()));

        $project->status = \Task4ItAPI\Project::COMPLETED;
        $project->save();

        $notificationTag = sprintf('freelancer-%s-notifications', $executer->id);
        $notificationMessage = sprintf('User has set the project %s as completed', $project->title);

        #push notification
        try {
            $executer = $project->executer()->first();
            \LPusher::trigger(
                $notificationTag,
                'project-completed',
                ['message' => $notificationMessage]);

            #TODO: also send email
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        try {
            $notification = [
                'tag' => 'project-completed',
                'message' => $notificationMessage,
                'ids' => [
                    'projectId' => $projectId
                ]
            ];
            $redisClient = app("PredisClient");
                $redisClient->lpush($notificationTag, json_encode($notification) );
                $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
            //\Redis::lpush($notificationTag, json_encode($notification) );
            //\Redis::set(sprintf('%s.unread', $notificationTag), 1);

        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        $emailData = [
            'subtitle' => 'A project you\'ve been working on was set as complete!',
            'title' => 'Project development',
            'body' => sprintf('The project %s has been set as complete. Go to: %s/projects/%s', $project->title, config('site_url'), $project->id),
        ];

        try {
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($executer, $emailData) {
                $m->to($executer->user->email, $executer->user->first_name)->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
        }

        $meta =  ['message' => sprintf('User has set the project %s as completed', $projectId)];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    protected function review(Request $request, $projectId, $reviewer = 'user')
    {
        $project = \Task4ItAPI\Project::find($projectId);
        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        $user = $project->user;
        $executer = $project->executer()->first();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find project user');
        }

        if (!$executer) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find project executer');
        }

        //validate if auth user is project user
        if ($reviewer == 'user') {
            $auth = app('Dingo\Api\Auth\Auth')->user();

            if ($auth->id != $user->id) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Only the owner of the project can review the freelancer');
            }
        } else {
            $freelancer = $user->professional;

            if ($freelancer->id != $executer->id) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('The executer of the project must be authenticated to review the user');
            }
        }

        $validator = Validator::make($request->all(), array(
            'title' => 'required|min:3',
            'review' => 'required|min:3',
            'stars' => 'required|integer|between:1,5',
        ));

        if ($validator->fails()) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException(
                'Could not add project review.',
                $this->translateErrors($validator->errors()->all())
            );
        }

        $reviewData = array(
            'project_id' => $project->id,
            'review' => $request->input('review'),
            'stars' => $request->input('stars'),
            'title' => $request->input('title')
        );

        try {
            if ($reviewer == 'user') {
                $executer->reviewsToMe()->attach($user->id, $reviewData);
            } elseif ($reviewer == 'freelancer') {
                //add new user review
                $user->reviewsToMe()->attach($executer->id, $reviewData);
            }
        } catch (Exception $e) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not add project review.', $e->getMessage());
        }

        #push notification
        try {
            if ($reviewer == 'user') {
                $notificationTag = sprintf('freelancer-%s-notifications', $executer->id);
                $notificationMessage = sprintf('User %s has just reviewed you.', $user->displayName());
            } elseif ($reviewer == 'freelancer') {
                $notificationTag = sprintf('user-%s-notifications', $user->id);
                $notificationMessage = sprintf('Freelancer %s has just reviewed you.', $executer->user->displayName());
            }

            \LPusher::trigger(
                $notificationTag,
                sprintf('%s-review', $reviewer),
                ['message' => $notificationMessage]);

            $notId = $reviewer == 'user' ? $user->id : $executer->user->id;

            $notification = [
                'tag' => sprintf('%s-review', $reviewer),
                'message' => $notificationMessage,
                'ids' => [
                    'userId' => $notId,
                ]
            ];
            $redisClient = app("PredisClient");
                $redisClient->lpush($notificationTag, json_encode($notification) );
                $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
            //\Redis::lpush($notificationTag, json_encode($notification) );
            //\Redis::set(sprintf('%s.unread', $notificationTag), 1);

            #TODO: also send email
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        $meta =  ['message' => 'Review done successfully'];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    //review to the user
    public function reviewUser(Request $request, $projectId)
    {
        return $this->review($request, $projectId, 'freelancer');
    }

    public function reviewFreelancer(Request $request, $projectId)
    {
        return $this->review($request, $projectId, 'user');
    }

    public function openProjects(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $keywords = $request->input("keywords");
        $category = $request->input("categories");
        $level = $request->input("level");
      
        $query = sprintf('
            select p.user_id, p.id, p.tag_id, pp.project_id, pp.professional_id from projects as p
                left outer join professional_project pp on pp.project_id = p.id
                where p.status = \'%s\' and (project_id is null
                or
                (project_id is not null and professional_id is null)
                or
                (professional_id is not null and accepted = 0))', \Task4ItAPI\Project::PUBLISHED);

        if($level != null && !empty($level) && is_numeric($level) && $level >= 1 && $level <= 4) {
            $query .= " AND p.level = ".$level;
        }


        if(!is_null($keywords) && !empty($keywords)){
          $query .= " AND (p.title like ? OR p.description like ? OR p.user_id IN (SELECT `id` FROM `users` WHERE `first_name` like ? OR `last_name` like ?))";
          $bindings = array("%".$keywords."%","%".$keywords."%","%".$keywords."%","%".$keywords."%");
          $result = \DB::select($query, $bindings);
        } else {
            $result = \DB::select($query);
        }
        //finds all open project ids

        $projectIds = array_map(function ($item) { return $item->id; }, $result);
        if($category != null && $category != 0) {
            $projects = \Task4ItAPI\Project::whereIn('id', $projectIds)->where("tag_id", "=", $category)->orderBy('created_at', 'desc')->paginate($request->input("per_page", config("pagination.per_page")));
        }
        else
            $projects = \Task4ItAPI\Project::whereIn('id', $projectIds)->orderBy('created_at', 'desc')->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );

    }

}
