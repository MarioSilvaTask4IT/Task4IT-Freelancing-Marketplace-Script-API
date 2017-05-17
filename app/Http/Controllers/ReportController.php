<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{

    public function reportUser(Request $request, $userId)
    {
        $user = \Task4ItAPI\User::find($userId);

        if (!$user) {
            return $this->response->errorNotFound('Could not find user ' . $userId);
        }

        $auth = app('Dingo\Api\Auth\Auth')->user();

        if ($auth->id == $userId) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('You cannot report yourself!');
        }

        $reportData = [];

        if ($request->input('reason')) {
            $reportData['reason'] = $request->input('reason');
        }

        try {
            $user->reportedBy()->attach($auth->id, $reportData);
        } catch (Exception $e) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not report because: ', $e->getMessage());
        }

        #send email to admin
        $emailData = [
            'subtitle' => sprintf('User %s Reported!', $user->first_name),
            'title' => 'User Reported',
            'body' => sprintf('User %s has been reported by %s', $user->first_name, $auth->first_name),
        ];

        try {
            \Log::debug(sprintf("Sending email to admin: %s", env('MAIL_TO_ADDRESS', 'user@user.com')));
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($emailData) {
                $m->to(env('MAIL_TO_ADDRESS', 'user@user.com'), config('mail.to.name'))->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", env('MAIL_TO_ADDRESS', 'user@user.com'), $e->getMessage()));
        }

        $meta = ['message' => 'User reported successfully'];

        return $this->response->item($user, new \Task4ItAPI\Http\Transformers\UserTransformer)->setMeta($meta);
    }

    public function reportProject(Request $request, $projectId)
    {
        $project = \Task4ItAPI\Project::find($projectId);

        if (!$project) {
            return $this->response->errorNotFound('Could not find project ' . $projectId);
        }

        $user = app('Dingo\Api\Auth\Auth')->user();

        $reportData = [];

        if ($request->input('reason')) {
            $reportData['reason'] = $request->input('reason');
        }

        try {
            $project->reportedBy()->attach($user->id, $reportData);
        } catch (Exception $e) {
            throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not report because: ', $e->getMessage());
        }

        #send email to admin
        $emailData = [
            'subtitle' => sprintf('Project %s Reported!', $project),
            'title' => 'Project Reported',
            'body' => sprintf('Project %s has been reported by %s', $project->id, $user->first_name),
        ];

        try {
            \Log::debug(sprintf("Sending email to admin: %s", env('MAIL_TO_ADDRESS', 'user@user.com')));
            \Queue::push(\Mail::send('emails.general', $emailData, function ($m) use ($emailData) {
                $m->to(env('MAIL_TO_ADDRESS', 'user@user.com'), config('mail.to.name'))->subject($emailData['title']);
            }));
        } catch (Exception $e) {
            \Log::error(sprintf("Could not send email to %s because %s", env('MAIL_TO_ADDRESS', 'user@user.com'), $e->getMessage()));
        }

        $meta = ['message' => 'Project reported successfully'];

        return $this->response->item($project, new \Task4ItAPI\Http\Transformers\ProjectTransformer)->setMeta($meta);
    }

    public function usersReported(Request $request)
    {
        $result = \DB::table('user_reports')->select(\DB::raw('DISTINCT(reported_id)'))->get();

        $userIds = array_map(function ($item) { return $item->reported_id; }, $result);

        $users = \Task4ItAPI\User::whereIn('id', $userIds)->paginate($request->input("per_page", config("pagination.per_page")));

        // Return a collection of $users with pagination
        return $this->response->paginator(
            $users,
            new \Task4ItAPI\Http\Transformers\UserTransformer
        );
    }

    public function projectsReported(Request $request)
    {
        $result = \DB::table('project_reports')->select(\DB::raw('DISTINCT(reported_id)'))->get();

        $projectIds = array_map(function ($item) { return $item->reported_id; }, $result);

        $projects = \Task4ItAPI\Project::whereIn('id', $projectIds)->paginate($request->input("per_page", config("pagination.per_page")));

        // Return a collection of $projects with pagination
        return $this->response->paginator(
            $projects,
            new \Task4ItAPI\Http\Transformers\ProjectTransformer
        );
    }

}
