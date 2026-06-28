<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ], [], [
            'email' => '邮箱',
            'password' => '密码',
        ]);
        $credentials['is_active'] = true;

        if (Auth::guard('admin')->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            $admin = Auth::guard('admin')->user();
            $admin->update(['last_login' => now()]);

            return redirect()->intended(route('dashboard.index'));
        }

        return back()->withErrors([
            'email' => '邮箱或密码错误，或者账号已停用。',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
