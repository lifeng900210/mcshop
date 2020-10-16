<?php


namespace App\Http\Controllers\Wx;


use App\CodeResponse;
use App\Http\Services\UserServices;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends WxController
{
    public function register(Request $request)
    {
        $username = $request->input('username', '');
        $password = $request->input('password', '');
        $mobile   = $request->input('mobile', '');
        $code     = $request->input('code', '');

        if (empty($username) || empty($password) || empty($mobile) || empty($code)) {
            return $this->fail(CodeResponse::PARAM_ILLEGAL);
        }

        $user = UserServices::getInstance()->getByUsername($username);

        if (!is_null($user)) {
            return $this->fail(CodeResponse::AUTH_NAME_REGISTERED);
        }

        $validate = Validator::make(['mobile' => $mobile], ['mobile' => 'regex:/^1[0-9]{10}$']);

        if ($validate->failed()) {
            return $this->fail(CodeResponse::AUTH_INVALID_MOBILE);
        }

        $user = UserServices::getInstance()->getByMobile($mobile);

        if (!is_null($user)) {
            return $this->fail(CodeResponse::AUTH_MOBILE_REGISTERED);
        }

        $avatarUrl = "https://yanxuan.nosdn.127.net/80841d741d7fa3073e0ae27bf487339f.jpg?imageView&quality=90&thumbnail=64x64";

        //验证验证码
        UserServices::getInstance()->checkCaptcha($mobile, $code);

        $user                  = new User();
        $user->username        = $username;
        $user->password        = Hash::make($password);
        $user->mobile          = $mobile;
        $user->avatar          = $avatarUrl;
        $user->nickname        = $username;
        $user->last_login_time = Carbon::now()->toDateTimeString();
        $user->last_login_ip   = $request->getClientIp();
        $user->add_time        = Carbon::now()->toDateTimeString();
        $user->update_time     = Carbon::now()->toDateTimeString();
        $user->save();

        //TODO 新用户发券
        return $this->success([
            'token'    => '124',
            'userinfo' => [
                'nickname' => $username,
                'avatar'   => $avatarUrl
            ]
        ]);

    }

    public function regCaptcha(Request $request)
    {
        $mobile   = $request->input('mobile');
        $validate = Validator::make(['mobile' => $mobile], ['mobile' => 'regex:/^1[0-9]{10}$']);

        if ($validate->failed()) {
            return $this->fail(CodeResponse::AUTH_INVALID_MOBILE);
        }

        $user = UserServices::getInstance()->getByMobile($mobile);

        if (!is_null($user)) {
            return $this->fail(CodeResponse::AUTH_MOBILE_REGISTERED);
        }

        $lock = Cache::add('register_captcha_lock_'.$mobile, 1, 60);

        if (!$lock) {
            return $this->fail(CodeResponse::AUTH_CAPTCHA_FREQUENCY);
        }

        $isPass = UserServices::getInstance()->checkMobileSendCaptchaCount($mobile, 10);

        if (!$isPass) {
            return $this->fail(CodeResponse::AUTH_CAPTCHA_FREQUENCY, '验证码每天发送不能超过10次');
        }

        $code = UserServices::getInstance()->setCaptcha($mobile);
        UserServices::getInstance()->sendCaptchaMsg($mobile, $code);
        return $this->success();
    }
}