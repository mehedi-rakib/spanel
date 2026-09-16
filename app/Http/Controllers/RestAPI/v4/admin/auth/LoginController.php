<?php

namespace App\Http\Controllers\RestAPI\v4\admin\auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $admin = Admin::where(['email' => $request['email']])->first();

        if (!isset($admin) || $admin->status != 1 || !auth('admin')->attempt(['email' => $request['email'], 'password' => $request['password']])) {
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('credentials does not match or your account has been suspended')]],
            ], 401);
        }

        $token = Str::random(50);
        Admin::where(['id' => $admin->id])->update(['auth_token' => $token]);

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'admin_role_id' => $admin->admin_role_id,
                'is_super_admin' => $admin->admin_role_id == 1,
                'module_access' => $admin->admin_role_id == 1 ? null : json_decode(optional($admin->role)->module_access ?? '[]'),
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        Admin::where(['id' => $request['admin']->id])->update(['auth_token' => null]);
        return response()->json(['message' => translate('logged out successfully')], 200);
    }

    public function me(Request $request)
    {
        $admin = $request['admin'];
        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'admin_role_id' => $admin->admin_role_id,
            'is_super_admin' => $admin->admin_role_id == 1,
            'module_access' => $admin->admin_role_id == 1 ? null : json_decode(optional($admin->role)->module_access ?? '[]'),
        ], 200);
    }
}
