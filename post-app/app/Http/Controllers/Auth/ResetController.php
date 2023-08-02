<?php

namespace App\Http\Controllers\Auth;

use App\Models\Meta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResetController extends Controller
{
    public function forgotPassword(Request $request)
    {
        if (!$request->has('email')) {
            return response()->json(['message' => 'Bad Request'], 400);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $isMobile = $request->input('mobile-app', false);

        if ($isMobile) {
            $meta = $this->generateToken($user->id, true);


            // Redis::publish(
            //     config('app.channel_prefix').'.password.reset.request-mobile',
            //     json_encode([
            //         'user' => $user->toArray(),
            //         'otp' => $meta->value,
            //     ])
            // );
        } else {
            $meta = $this->generateToken($user->id);

            // Redis::publish(
            //     config('app.channel_prefix').'.password.reset.request',
            //     json_encode([
            //         'user' => $user->toArray(),
            //         'verify_url' => config('app.front_end')."/auth/password-reset?token=".$meta->value,
            //     ])
            // );
        }


        return response()->json(["success" => true], 200);
    }

    public function passwordReset(Request $request)
    {
        $this->validate(
            $request,
            [
                'token' => 'required',
                'password' => 'required|min:6',
                'verify_password' => 'required|same:password',
            ]
        );

        $token = $request->token;
        // FIND USER meta with token
        $meta = $this->isVarifiedToken($token);

        if ($meta) {
            $user = User::findOrFail($meta->user_id);

            $user->password = Hash::make(request()->password);

            $user->save();

            Meta::updateOrCreate(
                ['user_id' => $user->id,'key' => 'last_password_reset_at'],
                ['value' => Carbon::now()]
            );


            // Redis::publish(
            //     config('app.channel_prefix').'.password.reset.done',
            //     json_encode([
            //         'user' => $user->toArray()
            //     ])
            // );

            return response()->json(["success" => true], 200);
        }

        return response()->json(["success" => false, "message" => 'Invalide token'], 422);
    }

    protected function isVarifiedToken(string $token)
    {
        $meta = Meta::withoutGlobalScope('visible')->where([
            'key' => 'password_reset_token',
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
    protected function generateToken($user_id = 0, $otp = false)
    {
        $meta = Meta::withoutGlobalScope('visible')->firstOrNew([
            'user_id' => $user_id,
            'key'     => 'password_reset_token'
        ]);

        if ($otp) {
            $meta->value = random_int(111111, 999999);
        } else {
            $meta->value = Str::random(64);
        }

        $meta->is_hidden = true;

        $meta->save();

        return $meta;
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function changePassword(Request $request, $userId)
    {
        $input = $request->all();
        $user = $request->user();

        $this->validate($request, [
            'old_password' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);


        try {
            if ((Hash::check(request('old_password'), $user->password)) == false) {
                $arr = array("status" => 400, "message" => "Check your old password.", "data" => array());
            } elseif ((Hash::check(request('new_password'), $user->password)) == true) {
                $arr = array("status" => 400, "message" => "Please enter a password which is not similar then current password.", "data" => array());
            } else {
                User::find($userId)->update(['password' => Hash::make($input['new_password'])]);
                $arr = array("status" => 200, "message" => "Password updated successfully.", "data" => array());
            }
        } catch (\Exception $ex) {
            if (isset($ex->errorInfo[2])) {
                $msg = $ex->errorInfo[2];
            } else {
                $msg = $ex->getMessage();
            }
            $arr = array("status" => 400, "message" => $msg, "data" => array());
        }

        // Redis::publish(
        //     config('app.channel_prefix').'.password.change',
        //     json_encode([
        //         'user' => User::find($userId)->toArray()
        //     ])
        // );

        return response()->json($arr, $arr['status']);
    }
}
