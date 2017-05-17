<?php

namespace Task4ItAPI\Http\Controllers;

use Carbon\Carbon as Carbon;
use Task4ItAPI\Http\Controllers\Controller as Controller;
use EllipseSynergie\ApiResponse\Contracts\Response as Response;

use Illuminate\Http\Request;
use Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $users = \Task4ItAPI\User::active()->paginate($request->input("per_page", config("pagination.per_page")));

        // Return a collection of $books with pagination
        return $this->response->paginator(
            $users,
            new \Task4ItAPI\Http\Transformers\UserTransformer
        );
    }

    public function getMessages(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        $messages = $user->messages()->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $messages,
            new \Task4ItAPI\Http\Transformers\MessageTransformer
        );
    }

    /**
     * Return info about the authenticated user
     * @return Response
     */
    public function me()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        return $this->show($user->id);
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($userId)
    {
        $user = \Task4ItAPI\User::find($userId);

        if (!$user || $user->active === 0) {
            return $this->response->errorNotFound('Could not find user ' . $userId);
        }

        // return $this->response->withItem($user, new \Task4ItAPI\Http\Transformers\UserTransformer);
        return $this->response->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), array(
            'email' => 'min:8|required|email|unique:users',
            'password' => 'min:6|required',
            'origin' => 'size:2',
            'first_name' => 'required',
        ));

        if ($validator->fails()) {
            $errors = $this->translateErrors($validator->errors()->all());

            \Log::info("REGISTER:: NOT validating user");

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not create user.', $errors);
        }

        $user = new \Task4ItAPI\User();
        $user->first_name = $request->input("first_name");
        $user->email = $request->input("email");
        $user->password = \Hash::make($request->input('password'));
        $user->active = 0;

        $lastName = $request->input("last_name");
        if ($lastName) {
            $user->last_name = $lastName;
        }

        $nif = $request->input("NIF", null);
        if (!is_null($nif)) {
            $user->NIF = $nif;
        }

        $aboutMe = $request->input("about_me", null);
        if (!is_null($aboutMe)) {
            $user->about_me = $aboutMe;
        }

        $occupation = $request->input("occupation", null);
        if (!is_null($occupation)) {
            $user->occupation = $occupation;
        }

        $origin = $request->input("origin", null);
        if (!is_null($origin)) {
            $user->origin = $origin;
        }

        //if it is a professional, create an entry in the professinals table here
        $isProfessional = $request->input('is_professional', null);
        if ($isProfessional) {
            $professional = new \Task4ItAPI\Professional();
            $professional->save();

            $tags = $request->input("tags", null);
            if (!is_null($tags)) {
                $professional->setTags($tags);
            }

            $user->professional()->associate($professional);

            #store how to receive payment when a freelancer
            $receive_payment_info = $request->input("receive_payment_info", null);
            if (!is_null($receive_payment_info)) {
                $user->receive_payment_info = $receive_payment_info;
            }
        }

	    $user->save();
        //return token
        $token = \JWTAuth::fromUser($user);

        $this->generateAndSendRegistrationToken($user);

        return $this->response->array(array('token' => $token));
    }

    /**
     * Transform a user in a professional
     * @param  Request $request
     * @param  type    $userId
     * @return type
     */
    public function makeProfessional(Request $request, $userId)
    {
        $user = \Task4ItAPI\User::find($userId);

        if (!$user) {
            return $this->response->errorNotFound('Could not find user ' . $userId);
        }

        if ($user->professional) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('User already a professional');
        }

        $professional = new \Task4ItAPI\Professional();
        $professional->save();

        $tags = $request->input("tags", null);
        if (!is_null($tags)) {
            $professional->setTags($tags);
        }

        $user->professional()->associate($professional);
        $user->save();

        \Log::error(sprintf("User %s is now associated with professional %s", $user->id, $professional->id));

        $meta = ['message' => 'User updated successfully'];

        return $this->response->withItem($user, new \Task4ItAPI\Http\Transformers\UserTransformer)->setMeta($meta);
    }

    public function generateAndSendRegistrationToken($user = null)
    {
        #no user received, use the logged user
        if (!$user) {
            $user = app('Dingo\Api\Auth\Auth')->user();
        }

        $token = \JWTAuth::fromUser($user);

        #set the token on redis
        $redisClient = app("PredisClient");
        $redisClient->set(sprintf('user.%s.registration.token', $user->id), $token);
        //\Redis::set(sprintf('user.%s.registration.token', $user->id), $token);


        $emailData = [
             'subtitle' => 'yeah, you\'re almost there!!!',
             'title'    => 'Please confirm your registration',
             'token'    => $token,
             'userId'   => $user->id,
        ];

        if ($user->email) {
            try {
                \Queue::push(\Mail::send('emails.registration', $emailData, function($m) use($user, $emailData) {
                    $m->to($user->email, $user->first_name)->subject($emailData['title']);
               }));
            } catch (Exception $e) {
                \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
            }
        }
    }

    public function resendtoken(\Request $request) {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if(\Redis::get("USER_".$user->id."_EMAIL_AUTH_SENT") != null && \Redis::get("USER_".$user->id."_EMAIL_AUTH_SENT") >= strtotime("-24 hours"))
            return json_encode(["status" => "error"]);

        \Redis::set("USER_".$user->id."_EMAIL_AUTH_SENT", time());

        $this->generateAndSendRegistrationToken();
    }

    public function validateRegistrationToken(\Request $request, $userId = null, $token = null)
    {
        $user = \Task4ItAPI\User::find($userId);

        if (!$user) {
            return $this->response->errorNotFound('Could not find user ' . $userId);
        }

        #get the token on redis
        $redisClient = app("PredisClient");
        //$redisClient->get(sprintf('user.%s.registration.token', $user->id));
        $ourToken = \Redis::get(sprintf('user.%s.registration.token', $user->id));

        $validated = 0;
        if ($token == $ourToken) {
            $validated = 1;

            $user->active = 1;
            $user->save();
        }

        return $this->response->array(array('validated' => $validated));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            return $this->response->errorNotFound('No user logged in');
        }

        $firstName = $request->input("first_name");
        if (!is_null($firstName)) {
            $user->first_name = $request->input("first_name");
        }

        $lastName = $request->input("last_name");
        if ($lastName) {
            $user->last_name = $lastName;
        }

        $image = $request->input("image");
        if ($image) {
            $user->image = $image;
        }

        $origin = $request->input("origin", null);
        if (!is_null($origin) && $user->change_country != 1) {
            $user->origin = $origin;
            $user->change_country = 1;
        }

        $wallet = $request->input("wallet", null);
        if (!is_null($wallet)) {
            $user->wallet= $wallet;
        }

        $nif = $request->input("NIF", null);
        if (!is_null($nif)) {
            $user->NIF = $nif;
        }

        $aboutMe= $request->input("about_me", null);
        if (!is_null($aboutMe)) {
            $user->about_me = $aboutMe;
        }

        $occupation = $request->input("occupation", null);
        if (!is_null($occupation)) {
            $user->occupation = $occupation;
        }

        $address = $request->input("address", null);
        if (!is_null($address)) {
            $user->address = $address;
        }

        $phone = $request->input("phone", null);
        if (!is_null($phone)) {
            $user->phone = $phone;
        }

        $email = $request->input("email", null);
        if (!is_null($email)) {
            $user->email = $email;
        }

        //allow to update tags if professional
        $tags = $request->input("tags", null);
        if (!is_null($tags) && $user->professional) {
            $user->professional->setTags($tags);
        }

        $receive_payment_info = $request->input("receive_payment_info", null);
        if (!is_null($receive_payment_info) && $user->professional) {
            $user->receive_payment_info = $receive_payment_info;
        }

        $linkedin = $request->input("linkedin", null);
        if (!is_null($linkedin)) {
            $user->linkedin = $linkedin;
        }

        $behance = $request->input("behance", null);
        if (!is_null($behance)) {
            $user->behance = $behance;
        }

        $github = $request->input("github", null);
        if (!is_null($github)) {
            $user->github = $github;
        }

        $skype = $request->input("skype", null);
        if (!is_null($skype)) {
            $user->skype = $skype;
        }

        $dribbble = $request->input("dribbble", null);
        if (!is_null($dribbble)) {
            $user->dribbble = $dribbble;
        }

        $youtube = $request->input("youtube", null);
        if (!is_null($youtube)) {
            $user->youtube = $youtube;
        }

        $personal_website = $request->input("personal_website", null);
        if (!is_null($personal_website)) {
            $user->personal_website = $personal_website;
        }

        $level = $request->input("level", null);
        if(empty($user->level)){
            $user->level = 1;
        }

        $user->save();

        $meta = ['message' => 'User updated successfully'];

        //first time register it has a trial period
        //is user professional it means freelancer
        /*
        if($user->professional){
            //register trial period
            //get trial plan
            $queryPP = \Task4ItAPI\PaymentPlans::query();
            $paymentPlans = $queryPP->get()->where("name", "Trial");

            $paymentPlan = $paymentPlans->first();
            $duration = $paymentPlan->duration;

            $todayDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime($todayDate." + {$duration} days"));
            $paymentId = "";

            $subscriptionController = new SubscriptionController();
            $subscriptionController->updateFreelancerSubscription($user, $paymentPlan, $expiryDate, $paymentId, true);

        }
        */
        return $this->response->withItem($user, new \Task4ItAPI\Http\Transformers\UserTransformer)->setMeta($meta);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function destroy($id)
    {
        //TODO: delete user and all relations

    }
     /**
     * My projects.
     *
     * @param  int      $userId
     * @return Response
     */
    public function projects(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user authenticated');
        }

        $projects = $user->projects()->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsCompleted(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user authenticated');
        }

        $projects = $user->projectsCompleted()->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsActive(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user authenticated');
        }

        $projects = $user->projectsActive()->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsPending(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user authenticated');
        }

        $projects = $user->projectsPending()->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

    public function projectsTotals($userId)
    {
        $user = \Task4ItAPI\User::find($userId);

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        $projects = $user->projectsTotals();

        return $this->response->array(['data' => $projects]);
    }

    public function myProjectsTotals()
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        return $this->projectsTotals($user->id);
    }

    //user creates a new project
    public function addProject(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        $validator = Validator::make($request->all(), array(
            'title' => 'min:2',
            'code'  => 'min:6|required',
            'tag'   => 'required|exists:tags,tag',
            'price' => 'numeric',
            'level' => 'numeric'
        ));

        if ($validator->fails()) {
            $errors = $this->translateErrors($validator->errors()->all());

            \Log::error("NOT validating project");

            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not add project.', $errors);
        }

        $tag = \Task4ItAPI\Tag::where('tag', '=', $request->input('tag'))->first();

        $project = new \Task4ItAPI\Project();
        $project->code = $request->input("code");
        $project->title = $request->input("title");
        $project->status = \Task4ItAPI\Project::PUBLISHED;

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
        $project->level = 1;
        if (!is_null($level)) {
            $project->level = $level;
        }


        $project->save();

        //add relationships
        $tag->projects()->save($project);
        $user->projects()->save($project);

        $meta = ['message' => 'Project created successfully'];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    public function chooseFreelancerForProject(Request $request, $projectId)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No project found');
        }

        if ($project->user_id != $user->id) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Project does not belong to user ' . $user->id);
        }

        if ($project->executer()->count()) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Project is already under way! Cannot set freelancer now!');
        }

        $professionalId = $request->input('professionalId');
        $professional = \Task4ItAPI\Professional::find($professionalId);

        if (!$professional) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Could not find freelancer ' . $projectId);
        }

        if (!$project->candidates()->where('professional_id', $professionalId)->count()) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('The freelancer has not yet applied to project!');
        }

        \Task4ItAPI\ProfessionalProject::where('professional_id', '=', $professional->id)
                    ->where('project_id', '=', $projectId)->update(array('accepted' => 1, 'start_date' => Carbon::now()));

        $project->status = \Task4ItAPI\Project::EXECUTING;
        $project->save();

        $notificationTag = sprintf('freelancer-%s-notifications', $professionalId);
        $notificationMessage = sprintf('You have been chosen to develop the project %s', $project->title);

        #push notification
        try {
            \LPusher::trigger(
                $notificationTag,
                'chosen-for-project',
                ['message' => $notificationMessage]);

        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        try {
            $notification = [
                'tag' => 'chosen-for-project',
                'message' => $notificationMessage,
                'ids' => [
                    'projectId' => $projectId
                ]
            ];
            $redisClient = app("PredisClient");
            $redisClient->lpush($notificationTag, json_encode($notification));
            $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
            //\Redis::lpush($notificationTag, json_encode($notification) );
            //\Redis::set(sprintf('%s.unread', $notificationTag), 1);
        } catch (Exception $e) {
            \Log::error("Could not send push notifications because ", $e->getMessage());
        }

        $emailData = [
            'subtitle' => 'You\'ve been chosen to develop a project!',
            'title' => 'Project development',
            'body' => sprintf('You can start developing the project %s. Go to: %s/projects/%s', $project->title, config('site_url'), $project->id),
        ];

        try {
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($professional, $emailData) {
                $m->to($professional->user->email, $professional->user->first_name)->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", $user->email, $e->getMessage()));
        }

        #also notify other freelancers
        #to let the know the project will be developed by someone else
        $candidates = $project->candidates;
        foreach ($candidates as $candidate) {
            if ($candidate->professional_id == $professionalId) {
                continue;
            }

            $notificationTag = sprintf('freelancer-%s-notifications', $candidate->professional_id);
            $notificationMessage = sprintf('Someone else has been chose to develop the project %s', $project->title);
            try {
                \LPusher::trigger(
                    $notificationTag,
                    'not-chosen-for-project',
                    ['message' => $notificationMessage]);

                    $notification = [
                        'tag' => $notificationTag,
                        'message' => $notificationMessage,
                        'ids' => [
                            'projectId' => $projectId
                        ]
                    ];

                     $redisClient = app("PredisClient");
            $redisClient->lpush($notificationTag, json_encode($notification));
            $redisClient->set(sprintf('%s.unread', $notificationTag), 1);
                //\Redis::lpush($notificationTag, json_encode($notification) );
                //\Redis::set(sprintf('%s.unread', $notificationTag), 1);
            } catch (Exception $e) {
                \Log::error("Could not send push notifications because ", $e->getMessage());
            }
        }

        $meta = ['message' => sprintf('Freelancer %s chosen for project successfully.', $professionalId)];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    public function projectsPerMonth(Request $request)
    {
        setlocale(LC_TIME, 'Portugal');
        $user = app('Dingo\Api\Auth\Auth')->user();
        $now = Carbon::now();
        Carbon::setLocale('pt');

        $year = $request->input("year", $now->year);

        $projects = $user->projectCounters($year);

        return $this->response->array(['data' => $projects]);

    }

    public function delete_account(Request $request){

        //validate user session
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user) {
            return $this->response->errorNotFound('No user logged in');
        }

        $user->email = "ACCOUNT_DELETE_".time().$user->email;
        $user->active = 0;

        if($user->update()){
            return $this->response->array(['delete_account' => true]);
        }

        return $this->response->array(['delete_account' => false]);
    }
}
