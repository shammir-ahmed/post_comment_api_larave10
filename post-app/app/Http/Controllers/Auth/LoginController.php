<?php

namespace App\Http\Controllers\Auth;

use Carbon\Carbon;
use App\Helpers\JWT;
use App\Models\User;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Auth\Access\AuthorizationException;

class LoginController extends Controller
{

    /**
     * login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($request->email === 'admin@rushdasoft.com') {
            throw new \Exception('You can not logged in with this email.');
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw new AuthorizationException("Incorrect email or password!");
        }

        if ($user && ! Hash::check($request->input('password'), $user->password)) {
            throw new AuthorizationException("Incorrect email or password!");
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw new AuthorizationException("User not active please validate your email.");
        }
        /**
         * TODO: MFA
         */

        $jwt = JWT::getJWT($user);
        $rtoken = Token::newToken($user);

        Cache::remember("users.$user->id", config('cache.ttl.jwt'), function () use ($user, $request) {
            $user->load('meta');

            $u = new UserResource($user);

            return $u->toArray($request);
        });

        return  response()->json(
            [
                'access_token' => $jwt,
                "refresh_token" => $rtoken,
            ],
            Response::HTTP_OK
        );
    }

    public function user(Request $request)
    {
        $user = $request->user();

        $user->load('meta');

        Cache::remember("users.$user->id", config('cache.ttl.jwt'), function () use ($user, $request) {
            $user->load('meta');

            $u = new UserResource($user);

            return $u->toArray($request);
        });

        return new UserResource($user);
    }


    public function refreshToken(Request $request)
    {
        if ($request->has('refresh_token')) {
            $token = $request->input('refresh_token');
        } else {
            $token = $this->getBearerToken($request);
        }


        if ($token) {
            $rtoken = Token::varifyToken($token);

            $user = User::find($rtoken->user_id);

            $jwt = JWT::getJWT($user);

            return  response()->json([
                'access_token' => $jwt,
                "refresh_token" => $rtoken->token
            ], Response::HTTP_OK);
        }

        throw new AuthorizationException("Invalid Token");
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        /**
         * TODO: redis cache for exsiting jwt to unauthenticate next time
         */
        $token = $this->getBearerToken($request);

        Cache::remember("users.$token", config('cache.ttl.jwt'), function () {
            return true;
        });

        Cache::forget("users.".auth()->user()->id);

        return  response()->json(['success' => true], Response::HTTP_OK);
    }


    private function getBearerToken($request)
    {
        $headers = $request->header('authorization');
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }



    public function varifyOTP(Request $request, $user)
    {
        $user = User::findOrFail($user);
        $otp = $user->meta()->withoutGlobalScope('visible')->where('key', 'phone_verification_otp')->first();

        if (
            $request->has('otp')
            &&
            strlen(intval($request->otp)) === 6
            &&
            $request->otp == $otp->value
        ) {
            $user->meta()->create([
                'key' => 'phone_verified_at',
                'value' => Carbon::now()
            ]);

            // Redis::publish(config('app.channel_prefix').'.phone.verified', $user);
        }

        return response()->json(["success" => true], 200);
    }
}
