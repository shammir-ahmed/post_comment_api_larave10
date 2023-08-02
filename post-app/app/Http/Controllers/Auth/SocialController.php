<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Token;
use Carbon\Carbon;
use App\Helpers\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Auth\Access\AuthorizationException;

class SocialController extends Controller
{

    /**
     * login api
     *
     * @return \Illuminate\Http\Response
     */
    public function authenticateWithFacebook(Request $request)
    {
        $this->validate($request, [
            'token' => 'required',
        ]);

        $response = Http::get('https://graph.facebook.com?access_token=EAAXF4uVUffYBANuQTE1kXsZAWhKa0rxxNVEbIZBnTijlGEzM62yZABcjUIWQiQfiKpWZB8wdJr9KfSCIECx3fDvBeoxNMjqCMnN3bZAapWpRcofsTB3Wn0Qt0EYWcd8QbprRy00h5UIuUfFi6DJDgG2QvdLNw9y3fG6XurNt9OSoDNdgQ0PEjKvu3i9GLWVIvlas8OUdic3Y80flZBn2C6pUknAgRz4W4674hbYMxBcT2duQq6Nfgb');
        dd($response);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw new AuthorizationException("Incorrect email or password!");
        }

        if ($user && ! Hash::check($request->input('password'), $user->password)) {
            throw new AuthorizationException("Incorrect email or password!");
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            throw new AuthorizationException("User suspended! contact to admin.");
        }
        /**
         * TODO: MFA
         */

        $jwt = JWT::getJWT($user);
        $rtoken = Token::newToken($user);


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

        return new UserResource($user);
    }


    public function refreshToken(Request $request)
    {
        if ($request->has('refresh_token')) {
            $token = $request->input('refresh_token');
        } else {
            $token = $this->getBearerToken($request->header('refresh_token'));
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
        if ($request->has('refresh_token')) {
            $token = $request->input('refresh_token');
        } else {
            $token = $this->getBearerToken($request->header('refresh_token'));
        }

        if ($token) {
            Token::where('token', $token)->delete();
        }

        return  response()->json(['success' => true], Response::HTTP_OK);
    }


    private function getBearerToken($token)
    {
        if (!empty($token)) {
            if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }


    public function varifyOTP(Request $request, $user)
    {
        $user = User::findOrFail($user);
        $otp = $user->meta()->where('key', 'phone_verification_otp')->first();

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
