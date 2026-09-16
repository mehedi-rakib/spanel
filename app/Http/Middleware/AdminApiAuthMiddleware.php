<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class AdminApiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = explode(' ', $request->header('authorization'));
        if (count($token) > 1 && strlen($token[1]) > 30) {
            $admin = Admin::where(['auth_token' => $token[1]])->first();
            if (isset($admin) && $admin->status == 1) {
                $request['admin'] = $admin;
                auth()->guard('admin')->setUser($admin);
                return $next($request);
            }
        }

        return response()->json([
            'auth-001' => translate('Your existing session token does not authorize you any more')
        ], 401);
    }
}
