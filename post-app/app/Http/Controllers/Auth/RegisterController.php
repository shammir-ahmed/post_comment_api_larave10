<?php

namespace App\Http\Controllers\Auth;

use App\Models\Meta;
use App\Models\User;
use App\Models\Token;
use Carbon\Carbon;
use App\Helpers\JWT;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    /**
     * user Register function
     *
     * @param Request $request
     * @return Response;
     */
    public function register(Request $request)
    {
        $varifiedMeta = null;

        $this->validate($request, [
            'email'           => 'required|email|unique:users,email',
            'username'        => 'nullable|unique:users,username',
            'password'        => 'required|string|min:6',
            'verify_password' => 'required|same:password',
            'roles'             => 'nullable|in:buyer,owner,broker,vendor'
        ]);

        $isMobile = $request->input('mobile-app', false);

        $userData = $request->all();
        $userData['status'] = User::STATUS_UNVERIFIED;
        $userData['password'] = Hash::make($request->input('password'));
        $userData['user_type'] = $request->roles;
        if (empty($userData['username'])) {
            $userData['username'] = generate_username($userData);
        }



        if ($request->input('token')) {
            $varifiedMeta = $this->isVerifiedToken($request->input('token'));

            if ($varifiedMeta) {
                $userData['status'] = User::STATUS_ACTIVE;
                $userData['email'] = $varifiedMeta->key;
            }
        }

        $user = User::create($userData);



        if ($user && $varifiedMeta) {
            // Email already varified send welcome message
            if (in_array($request->roles, ['buyer','owner','vendor'])) {
                $user->assignRole($request->roles);
            }


            Meta::create([
                'user_id' => $user->id,
                'key' => 'email_varify_at',
                'value' => Carbon::now()
            ]);

            // Delete the varified token
            $varifiedMeta->delete();

            // Redis::publish(
            //     config('app.channel_prefix').'.registered',
            //     json_encode([
            //         'user' => $user->toArray(),
            //     ])
            // );

            event(new Registered($user) );

        } else {
            // Email not varified send a vrify mail with token

            if ($isMobile) {

                $meta = $this->generateToken($user->email, $user->id, true);

                // Redis::publish(
                //     config('app.channel_prefix').'.verify-mobile',
                //     json_encode([
                //         'user'  => $user->toArray(),
                //         'otp' => $meta->value,
                //         'email' => $user->email
                //     ])
                // );

            } else {
                $meta = $this->generateToken($user->email, $user->id);

                // Redis::publish(
                //     config('app.channel_prefix').'.verify',
                //     json_encode([
                //         'user'  => $user->toArray(),
                //         'verify_url' => config('app.front_end')."/auth/verify/varify-email/".$meta->value . "?email=".$user->email,
                //         'email' => $user->email
                //     ])
                // );
            }

        }

        $jwt = JWT::getJWT($user);
        $rtoken = Token::newToken($user);


        return  response()->json([
            "data" => [
                'auth_token' => $jwt,
                'refresh_token' => $rtoken,
                'user' => new UserResource($user)
            ]

        ], Response::HTTP_OK);
    }


    /**
     * invire user by email email must be giben.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function inviteByEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|unique:users,email',
        ]);

        $meta = $this->generateToken($request->input('email'));

        // Redis::publish(
        //     config('app.channel_prefix').'.invitation',
        //     json_encode([
        //         'user' => [
        //             'email' => $meta->key
        //         ],
        //         'invite_url' => config('app.front_end')."/auth/register/?email=".$request->input('email'). "&token=" .$meta->value
        //     ])
        // );
    }


    /**
     * Check token exists in meta
     *
     * @param string $token
     * @return \App\Models\Meta|null
     */
    protected function isVerifiedToken(string $token)
    {
        $meta = Meta::withoutGlobalScope('visible')->where([
                    'value'=> $token
                ])->first();

        if ($meta && Carbon::parse($meta->updated_at)->diffInMinutes(Carbon::now()) <= config('auth.verified_token_expire_in')) {
            return $meta;
        }
        return null;
    }

    /**
     * generate email varification Token
     *
     * @param string $email
     * @param integer $user_id
     * @return Meta
     */
    protected function generateToken(string $email, $user_id = 0, $opt = false)
    {
        $meta = Meta::withoutGlobalScope('visible')->firstOrNew([
            'user_id' => $user_id,
            'key'     => $email,
        ]);

        if ($opt) {
            $meta->value = random_int(111111, 999999);
        } else {
            $meta->value = Str::random(64);
        }


        $meta->is_hidden = true;

        $meta->save();

        return $meta;
    }

    public function resendEmail(Request $request) {
        $user = $request->user();

        if ($user->status !== User::STATUS_UNVERIFIED) {
            return  response()->json([
                "message" => "Already varified",
                "redirect" => true
            ], Response::HTTP_OK);
        }

        $isMobile = $request->input('mobile-app', false);

        if ($isMobile) {

             // Email not varified send a vrify mail with token
            $meta = $this->generateToken($user->email, $user->id, true);

            // Redis::publish(
            //     config('app.channel_prefix').'.verify-mobile',
            //     json_encode([
            //         'user'  => $user->toArray(),
            //         'otp' => $meta->value,
            //         'email' => $user->email
            //     ])
            // );

        } else{
             // Email not varified send a vrify mail with token
            $meta = $this->generateToken($user->email, $user->id);

            // Redis::publish(
            //     config('app.channel_prefix').'.verify',
            //     json_encode([
            //         'user'  => $user->toArray(),
            //         'verify_url' => config('app.front_end')."/auth/verify/varify-email/".$meta->value . "?email=".$user->email,
            //         'email' => $user->email
            //     ])
            // );
        }



        return  response()->json([
            "message" => "Resend email"
        ], Response::HTTP_OK);
    }


    /**
     * @param $email
     * @param $token
     * @return \Illuminate\Http\Response
     */
    public function verifyEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required',
            'token' => 'required',
        ]);

        $meta = $this->isVerifiedToken($request->input('token'));

        if ($meta && $meta->key === $request->input('email')) {
            // Token is valid

            $user = User::findOrFail($meta->user_id);

            $user->status = User::STATUS_ACTIVE;

            Meta::create([
                'user_id' => $user->id,
                'key' => 'email_varify_at',
                'value' => Carbon::now()
            ]);

            $user->save();

            if (in_array($user->user_type, ['buyer','owner','vendor'])) {
                $user->assignRole($user->user_type);
            }

            if ($role && $role->value) {
                $user->assignRole($role->value);
            }

            $meta->delete();

            // Redis::publish(
            //     config('app.channel_prefix').'.registered',
            //     json_encode([
            //         'user' => $user->toArray()
            //     ])
            // );

            event(new Registered($user) );

            return response()->json(["success" => true, 'redirect' => 'login'], Response::HTTP_OK);
        }

        return response()->json(["success" => false], Response::HTTP_NOT_ACCEPTABLE);
    }


    public function checkEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|unique:users,email',
        ]);

        return response()->json(["success" => true], Response::HTTP_OK);
    }

    public function checkUser(Request $request)
    {
        $this->validate($request, [
            'username' => 'required|unique:users,username',
        ]);

        return response()->json(["success" => true], Response::HTTP_OK);
    }


    public function checkPhone(Request $request)
    {
        $this->validate($request, [
            'phone' => 'required|unique:users,username',
        ]);

        return response()->json(["success" => true], Response::HTTP_OK);
    }
}
