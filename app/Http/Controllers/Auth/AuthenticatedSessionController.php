<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if ($user !== null && $user->isSuperAdmin() && $user->hasTwoFactorEnabled()) {
            Auth::logout();

            $request->session()->put(TwoFactorSession::LOGIN_USER_ID, $user->id);
            $request->session()->put(TwoFactorSession::LOGIN_REMEMBER, $request->boolean('remember'));

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();

        if ($user !== null && $user->isSuperAdmin()) {
            if (config('security.require_super_admin_two_factor', true) && ! $user->hasTwoFactorEnabled()) {
                return redirect()->intended(route('admin.two-factor.index', absolute: false));
            }

            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
