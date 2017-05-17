<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/paymill/subscription', '\Task4ItAPI\Http\Controllers\PaymentController@subscriptionWebHook');

#open endpoints
Route::post('authenticate', 'AuthenticateController@authenticate');
$api = app('api.router');


#not logged
$api->version('v1', function ($api) {
    $api->get('tags', 'Task4ItAPI\Http\Controllers\TagController@index');
    $api->get('offers', 'Task4ItAPI\Http\Controllers\OfferController@index');
    $api->get('offers/{id}', '\Task4ItAPI\Http\Controllers\OfferController@show');
    $api->post('users', '\Task4ItAPI\Http\Controllers\UserController@store');
    $api->post('users/{id}/make/professional', '\Task4ItAPI\Http\Controllers\UserController@makeProfessional');

    $api->get('users/{id}/validateToken/{token}', '\Task4ItAPI\Http\Controllers\UserController@validateRegistrationToken');

#    $api->post('webhooks/paymill/subscription', 'Task4ItAPI\Http\Controllers\PaymentController@subscriptionWebHook');
});

#logged endpoints
$api->version('v1', ['protected' => true], function ($api) {
    //generic routes available for both users and freelancers
    $api->get('resendToken', '\Task4ItAPI\Http\Controllers\UserController@resendtoken');
    $api->get('users', '\Task4ItAPI\Http\Controllers\UserController@index');
    $api->get('users/{id}', '\Task4ItAPI\Http\Controllers\UserController@show');
    $api->get('users/{id}/projects/totals', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projectsTotals'));
    $api->get('users/{id}/reviewsTo', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviewsToUser'));
    $api->get('professionals', '\Task4ItAPI\Http\Controllers\ProfessionalController@index');
    $api->get('professionals/{id}', '\Task4ItAPI\Http\Controllers\ProfessionalController@show');
    $api->get('professionals/{id}/projects/totals', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@projectsTotals'));
    $api->get('professionals/{id}/reviewsTo', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviewsToFreelancer'));
    $api->get('users/{id}/validateToken/{token}', '\Task4ItAPI\Http\Controllers\UserController@validateRegistrationToken');

    //open projects from everyone
    $api->get('projects/open', 'Task4ItAPI\Http\Controllers\ProjectController@openProjects');
    $api->get('projects/{id}', 'Task4ItAPI\Http\Controllers\ProjectController@show');
    $api->post('projects/{id}', 'Task4ItAPI\Http\Controllers\ProjectController@update');

    #all projects independently of state
    $api->get('projects', 'Task4ItAPI\Http\Controllers\ProjectController@index');
    $api->delete('projects/{id}', 'Task4ItAPI\Http\Controllers\ProjectController@destroy');

    $api->get('tags/{id}', 'Task4ItAPI\Http\Controllers\TagController@show');

    $api->get('me', '\Task4ItAPI\Http\Controllers\UserController@me');
    $api->post('me/update', '\Task4ItAPI\Http\Controllers\UserController@update');

    $api->post('me/addProject', array('uses' => '\Task4ItAPI\Http\Controllers\UserController@addProject'));
    //receives projectId and professionalId
    $api->post('me/project/{id}/chooseFreelancer', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@chooseFreelancerForProject'));

    $api->post('me/project/{id}/close', array('uses' => 'Task4ItAPI\Http\Controllers\ProjectController@close'));

    //the user and freelancer reviews are always referent to a project
    //user reviews the freelancer
    $api->post('me/project/{id}/review/freelancer', array('uses' => 'Task4ItAPI\Http\Controllers\ProjectController@reviewFreelancer'));

    $api->get('me/projects/counters', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projectsPerMonth'));

    $api->get('me/messages', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@getMessages'));

    //reporting
    $api->post('me/report/user/{id}', array('uses' => 'Task4ItAPI\Http\Controllers\ReportController@reportUser'));
    $api->post('me/report/project/{id}', array('uses' => 'Task4ItAPI\Http\Controllers\ReportController@reportProject'));

    $api->get('me/projects/totals', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@myProjectsTotals'));

    $api->post('contact_us', array('uses' => '\Task4ItAPI\Http\Controllers\MessageController@sendToAdmin'));

    $api->get('me/notifications', array('uses' => 'Task4ItAPI\Http\Controllers\NotificationController@userNotifications'));
    $api->get('me/notifications/toRead', array('uses' => 'Task4ItAPI\Http\Controllers\NotificationController@isUnreadUser'));

    $api->get('me/do_payment', array('uses' => 'Task4ItAPI\Http\Controllers\PaymentController@doPayment'));

    //delete account
    $api->post('me/delete', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@delete_account'));

    // Chat
    $api->post('me/project/{id}/chat/user', array('uses' => 'Task4ItAPI\Http\Controllers\ChatController@chatToUser'));
    $api->post('me/project/{id}/chat/freelancer', array('uses' => 'Task4ItAPI\Http\Controllers\ChatController@chatToFreelancer'));

    #for a non-freelancer user
    $api->group(['middleware' => 'user'], function ($api) {
        #all my projects
        $api->get('me/projects', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projects'));
        #my completed projects
        $api->get('me/projects/completed', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projectsCompleted'));
        #my open active (under development) projects
        $api->get('me/projects/active', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projectsActive'));
        #my pending projects (still not in development)
        $api->get('me/projects/pending', array('uses' => 'Task4ItAPI\Http\Controllers\UserController@projectsPending'));

        $api->get('me/reviews', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviews'));
        $api->get('me/reviewsToMe', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviewsToMe'));

    });

    //freelancer specific endpoints
    $api->group(['middleware' => 'freelancer'], function ($api) {
        //receives professionalId and project Id
        $api->post('me/project/{id}/apply', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@applyToProject'));
        $api->post('me/project/{id}/confirm/payment', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@confirmPayment'));
        $api->post('me/setTags', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@setTags'));

        //the user and freelancer reviews are always referent to a project
        //freelancer reviews the user
        $api->post('project/{id}/review/user', array('uses' => 'Task4ItAPI\Http\Controllers\ProjectController@reviewUser'));

        $api->get('me/freelancer/projects/completed', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@projectsFinished'));
        $api->get('me/freelancer/projects/active', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@projectsWon'));
        $api->get('me/freelancer/projects/pending', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@projectsPending'));

        $api->get('me/freelancer/projects/totals', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@myProjectsTotals'));

        $api->get('me/freelancer/projects/completed/counters', array('uses' => 'Task4ItAPI\Http\Controllers\ProfessionalController@projectsFinishedCounters'));

        $api->get('me/freelancer/reviews', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviewsFreelancer'));
        $api->get('me/freelancer/reviewsToMe', array('uses' => 'Task4ItAPI\Http\Controllers\ReviewController@reviewsToMeFreelancer'));

        $api->get('me/freelancer/notifications', array('uses' => 'Task4ItAPI\Http\Controllers\NotificationController@freelancerNotifications'));
        $api->get('me/freelancer/notifications/toRead', array('uses' => 'Task4ItAPI\Http\Controllers\NotificationController@isUnreadFreelancer'));
        
        //freelancer subscription cardinity route
        $api->post('me/freelancer/paySubscription', array('uses' => 'Task4ItAPI\Http\Controllers\SubscriptionController@paySubscription'));
        $api->post('me/freelancer/finalizePaymentSubscription', array('uses' => 'Task4ItAPI\Http\Controllers\SubscriptionController@finalizePaymentSubscription'));    
        //freelancer subscription load payments plan
        $api->get('me/freelancer/subscritionsPlan', array('uses' => 'Task4ItAPI\Http\Controllers\SubscriptionController@subscriptionsPlan'));
    });


    //admin specific endpoints
    $api->group(['middleware' => 'admin'], function ($api) {
        $api->post('admin/tags', 'Task4ItAPI\Http\Controllers\TagController@store');
        $api->post('admin/send/message', 'Task4ItAPI\Http\Controllers\MessageController@send');

        #list reported projects and users
        $api->get('admin/users/reported', 'Task4ItAPI\Http\Controllers\ReportController@usersReported');
        $api->get('admin/projects/reported', 'Task4ItAPI\Http\Controllers\ReportController@projectsReported');
    });
});

/*

// Route::resource('project', 'ProjectController');
// Route::resource('user', 'UserController');
Route::resource('tag', 'TagController');//TODO: limit creation of tags to admin
Route::resource('professional', 'ProfessionalController');
Route::resource('professionalproject', 'ProfessionalProjectController');

*/
