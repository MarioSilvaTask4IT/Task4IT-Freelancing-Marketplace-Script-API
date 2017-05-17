<?php

namespace Task4ItAPI\Http\Controllers;

use Carbon\Carbon as Carbon;
use EllipseSynergie\ApiResponse\Contracts\Response as Response;
use Illuminate\Http\Request;
use Task4ItAPI\Http\Controllers\Controller as Controller;
use Task4ItAPI\Project;

class ProfessionalController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return Response
    */
    public function index(Request $request)
    {
        $professionals = \Task4ItAPI\Professional::paginate($request->input("per_page", config("pagination.per_page")));

        // Return a collection of $books with pagination
        return $this->response->paginator(
            $professionals,
            new \Task4ItAPI\Http\Transformers\ProfessionalTransformer
        );
    }

    /**
    * Store a newly created resource in storage.
    *
    * @return Response
    */
    public function store()
    {
        //TODO: implement this
    }

    /**
    * Display the specified resource.
    *
    * @param  int  $professionalId
    * @return Response
    */
    public function show($professionalId)
    {
        $professional = \Task4ItAPI\Professional::find($professionalId);

        if (!$professional) {
            return $this->response->errorNotFound('Could not find professional ' . $professionalId);
        }

        return $this->response->withItem($professional, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer);
    }

    /**
     * Sets freelancer tags
     * @param Request $request
     * @param int     $professionalId
     *
     * @return Response
     */
    public function setTags(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        //must receive a comma separated list of tags
        $tags = $request->input('tags', null);

        if (!$tags) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Tags must be a comma separated list');
        }

        $professional->setTags($tags);

        $meta = ['message' => 'Tags setted successfully'];

        return $this->response->item($professional, new \Task4ItAPI\Http\Transformers\ProfessionalTransformer)->setMeta($meta);
    }

    public function confirmPayment($projectId)
    {
        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if (!$project->user) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Project without user, aborting!');
        }

        if ($project->status !== Project::COMPLETED) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot confirm payment to uncompleted project!');
        }

        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        if ($user->id == $project->user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot confirm payment to project created by yourself');
        }

        $executer = $project->executer()->first();

        if ($executer->id != $professional->id) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot confirm payment to project you have not developed');
        }

        try {
            \Task4ItAPI\ProfessionalProject::where('professional_id', '=', $professional->id)
                        ->where('project_id', '=', $projectId)->update(array('payment_confirmed' => 1, 'payment_confirmation_date' => Carbon::now()));
        } catch (Exception $e) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Error while professional ' . $professional->id . ' confirming payment to ' . $projectId);
        }

        $notificationTag = sprintf('user-%s-notifications', $project->user->id);
        $notificationMessage = sprintf('Freelancer %s has just confirmed payment to project %s.', $user->displayName(), $project->title);

        try {
            \LPusher::trigger(
                $notificationTag,
                'project-confirm-payment',
                ['message' => $notificationMessage]);
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        try {
            $notification = [
                'tag' => 'project-confirm-payment',
                'message' => $notificationMessage,
                'ids' => [
                    'professionalId' => $professional->id,
                    'projectId' => $project->id,
                    'userId' => $user->id,
                ],
            ];
            $redisClient = app("PredisClient");
                $redisClient->lpush($notificationTag, json_encode($notification) );
                $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
            //\Redis::lpush($notificationTag, json_encode($notification) );
            //\Redis::set(sprintf('%s.unread', $notificationTag), 1);

        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        $meta = ['message' => 'Payment confirmation to project done successfully'];

        return $this->response->item(
            $professional,
            new \Task4ItAPI\Http\Transformers\ProfessionalTransformer
        )->setMeta($meta);
    }

    public function applyToProject($projectId)
    {
        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if (!$project->user) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Project without user, aborting!');
        }

        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }
        
        $validSubscription = true;
        
        if(isset($user->freelancerSubscriptions)){
            $paymentSubscriptions = $user->freelancerSubscriptions;
            $expiryDate = $paymentSubscriptions->expiry_date;
            
            $today = date("Y-m-d");
            if($today <= $expiryDate){
                $validSubscription = true;
            }
        }
        if($validSubscription === false){
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Your subcription plan is not active.');
        }

        if ($user->id == $project->user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot apply to project created by yourself');
        }

        //avoid applying multiple times to the same project.
        $hasApplied = $project->candidates()->where('professionals.id', '=', $professional->id)->first();

        if ($hasApplied) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Cannot apply to the same project twice');
        }

        //set the relation
        try {
            $project->candidates()->attach($professional->id);
        } catch (Exception $e) {
            \Log::error('Could not apply to project because: ' . $e->getMessage());

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Error while professional ' . $professional->id . ' applying to ' . $projectId);
        }

        $notificationTag = sprintf('user-%s-notifications', $project->user->id);
        $notificationMessage = sprintf('Freelancer %s has just applied to project %s.', $user->displayName(), $project->title);

        try {
            \LPusher::trigger(
                $notificationTag,
                'project-apply',
                ['message' => $notificationMessage]);
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        try {
            $notification = [
                'tag' => 'project-apply',
                'message' => $notificationMessage,
                'ids' => [
                    'professionalId' => $professional->id,
                    'projectId' => $project->id,
                    'userId' => $user->id,
                ],
            ];
            $redisClient = app("PredisClient");
                $redisClient->lpush($notificationTag, json_encode($notification) );
                $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
            //\Redis::lpush($notificationTag, json_encode($notification) );
            //\Redis::set(sprintf('%s.unread', $notificationTag), 1);

        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        $meta = ['message' => 'Application to project done successfully'];

        return $this->response->item(
            $professional,
            new \Task4ItAPI\Http\Transformers\ProfessionalTransformer
        )->setMeta($meta);
    }

    public function projectsFinishedCounters(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        setlocale(LC_TIME, 'Portugal');
        $now = Carbon::now();
        Carbon::setLocale('pt');

        #use supplied year or this year if none supplied
        $year = $request->input("year", $now->year);

        $projects = $professional->projectsFinishedCounters($year);

        return $this->response->array(['data' => $projects]);
    }

    public function projectsFinished(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        $projects = $professional->projectsFinished()->paginate($request->input("per_page", config("pagination.per_page")));

        //TODO: order these projects
        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsWon(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        $projects = $professional->projectsWon()->paginate($request->input("per_page", config("pagination.per_page")));

        //TODO: order these projects
        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsApplied(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        $projects = $professional->projectsApplied()->paginate($request->input("per_page", config("pagination.per_page")));

        //TODO: order these projects
        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsPending(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No freelancer found');
        }

        $projects = $professional->projectsPending()->paginate($request->input("per_page", config("pagination.per_page")));

        //TODO: order these projects
        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsTotals($professionalId)
    {
        $professional = \Task4ItAPI\Professional::find($professionalId);

        if (!$professional) {
            return $this->response->errorNotFound('Could not find professional ' . $professionalId);
        }

        $projects = $professional->projectsTotals();

        return $this->response->array(['data' => $projects]);
    }

    public function myProjectsTotals()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        return $this->projectsTotals($professional->id);
    }
}
