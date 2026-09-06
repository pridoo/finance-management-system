<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (auth()->attempt($credentials, $request->boolean('remember'))) {
            // Checked after the password rather than before, so a wrong password
            // and a disabled account are indistinguishable to someone guessing
            // at which addresses exist.
            if (! auth()->user()->is_active) {
                auth()->logout();

                return back()->withErrors([
                    'email' => 'This account has been disabled. Please contact your administrator.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(auth()->user()->homeRoute());
        }

        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
