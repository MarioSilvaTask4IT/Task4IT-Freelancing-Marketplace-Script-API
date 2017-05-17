<?php

namespace Task4ItAPI\Http\Controllers;
use Task4ItAPI\Http\Controllers\Controller as Controller;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function reviews(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        return $this->reviewList($request, $user->reviews());
    }

    public function reviewsToMe(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        return $this->reviewList($request, $user->reviewsToMe());
    }

    public function reviewsFreelancer(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        $professional = $user->professional;

        return $this->reviewList($request, $professional->reviews());
    }

    public function reviewsToMeFreelancer(Request $request)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();
        $professional = $user->professional;

        return $this->reviewList($request, $professional->reviewsToMe());
    }

    public function reviewsToUser(Request $request, $userId)
    {
        $user = \Task4ItAPI\User::find($userId);
        if (!$user) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No user found');
        }

        return $this->reviewList($request, $user->reviewsToMe());
    }

    public function reviewsToFreelancer(Request $request, $professionalId)
    {
        $professional = \Task4ItAPI\Professional::find($professionalId);

        if (!$professional) {
            return $this->response->errorNotFound('Could not find professional ' . $professionalId);
        }

        return $this->reviewList($request, $professional->reviewsToMe());
    }

    protected function reviewList(Request $request, $collection)
    {
        $reviews = $collection->paginate($request->input("per_page", config("pagination.per_page")));

        return $this->response->paginator(
            $reviews,
            new \Task4ItAPI\Http\Transformers\ReviewTransformer
        );
    }
}
