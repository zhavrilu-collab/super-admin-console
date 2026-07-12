<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorLoginRequest;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(TwoFactorSession::LOGIN_USER_ID)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(TwoFactorLoginRequest $request): RedirectResponse
    {
        $user = User::query()->find($request->session()->get(TwoFactorSession::LOGIN_USER_ID));

        if ($user === null || ! $user->isSuperAdmin() || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget([
                TwoFactorSession::LOGIN_USER_ID,
                TwoFactorSession::LOGIN_REMEMBER,
            ]);

            return redirect()->route('login');
        }

        if (! $request->passesTwoFactorChallenge($user, $this->twoFactorAuthenticationService)) {
            return back()->withErrors([
                'code' => 'Kôd nije ispravan.',
            ]);
        }

        Auth::login(
            $user,
            (bool) $request->session()->get(TwoFactorSession::LOGIN_REMEMBER, false),
        );

        $request->session()->forget([
            TwoFactorSession::LOGIN_USER_ID,
            TwoFactorSession::LOGIN_REMEMBER,
        ]);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }
}
