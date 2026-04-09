<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        /** @var \Illuminate\View\View $view */
        $view = view('auth.login');
        return $view;
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        /** @var \Illuminate\Auth\SessionGuard */
        $guard = Auth::guard('single');
        if ($guard->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var \Illuminate\Auth\SessionGuard */
        $guard = Auth::guard('single');
        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
