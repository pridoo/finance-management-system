<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Handles signing in and signing out of the system.
 *
 * Routes (routes/web.php):
 *  - GET  /login  -> showLoginForm()  (route name: login, guests only)
 *  - POST /login  -> login()          (guests only)
 *  - POST /logout -> logout()         (route name: logout, signed-in users only)
 *
 * The root URL "/" also redirects to /login, so this is the first page
 * every user sees.
 */
class LoginController extends Controller
{
    /**
     * Show the login page.
     *
     * Called when a guest opens /login (or the site root "/").
     * Returns the auth.login view (resources/views/auth/login.blade.php).
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Check the email and password submitted from the login form.
     *
     * Called when the login form is submitted (POST /login).
     * On success: redirects the user to the page they first tried to open,
     * or to their home page (admins -> Users page, MSWDO users -> their
     * first allowed module, usually the Dashboard).
     * On failure: redirects back to the login page with an error message.
     */
    public function login(Request $request)
    {
        // $credentials = the validated email and password typed in the form.
        // Both are required, and the email must look like a real email address.
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // attempt() checks the email/password against the users table.
        // The second argument is the "Remember me" checkbox: if ticked, the
        // user stays signed in even after closing the browser.
        if (auth()->attempt($credentials, $request->boolean('remember'))) {
            // Checked after the password rather than before, so a wrong password
            // and a disabled account are indistinguishable to someone guessing
            // at which addresses exist.
            if (! auth()->user()->is_active) {
                auth()->logout();

                // onlyInput('email') refills the email box but never the password.
                return back()->withErrors([
                    'email' => 'This account has been disabled. Please contact your administrator.',
                ])->onlyInput('email');
            }

            // Give the user a fresh session ID after login (security measure
            // against session fixation attacks).
            $request->session()->regenerate();

            // intended() = the page the user wanted before being sent to login;
            // homeRoute() (in the User model) is the fallback landing page.
            return redirect()->intended(auth()->user()->homeRoute());
        }

        // Wrong email or password: go back to the form with one general message.
        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    /**
     * Sign the current user out.
     *
     * Called when the user clicks "Logout" (POST /logout).
     * Clears the session and returns a redirect to the login page.
     */
    public function logout(Request $request)
    {
        auth()->logout();
        // Destroy all session data and issue a new CSRF token so the old
        // session can no longer be reused.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
