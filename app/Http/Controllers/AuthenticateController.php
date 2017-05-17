<?php

namespace Task4ItAPI\Http\Controllers;

use Illuminate\Http\Request;

class AuthenticateController extends Controller
{
    public function authenticate(Request $request)
    {
        $credentials = $request->only('email', 'password', 'access_token');

        \Log::error('credentials');
        \Log::error($credentials);

        try {
            if (isset($credentials['password'])) {
                // verify the credentials and create a token for the user
                $token = \JWTAuth::attempt(array('email' => $credentials['email'], 'password' => $credentials['password']));

                if (! $token ) {
                    return response()->json(['error' => 'invalid_credentials'], 401);
                }
            } else {
                $identity = \Task4ItAPI\OauthIdentities::where(
                        'access_token', '=', $credentials['access_token']
                        )->first();

                if (!$identity) {
                    \Log::error('could not create token!!!!');

                    return response()->json(['error' => 'could_not_create_token'], 500);
                }

                $userId = $identity->user_id;

                $user = \Task4ItAPI\User::find($userId);

                $token = \JWTAuth::fromUser($user);
            }

        } catch (\JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        \Log::error('token');
        \Log::error($token);

        // if no errors are encountered we can return a JWT
        return response()->json(compact('token'));
    }
}
