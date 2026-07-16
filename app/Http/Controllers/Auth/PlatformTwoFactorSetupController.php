<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\Identity\PlatformAuthService;
use App\Support\PlatformLoginSession;
use App\Support\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PlatformTwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $this->resolvePlatformUser($request);

        if ($user === null) {
            return redirect()
                ->route('platform.login')
                ->withErrors(['email' => 'Za postavljanje 2FA potrebna je aktivna platform sesija.']);
        }

        $setupSecret = $request->session()->get(TwoFactorSession::SETUP_SECRET);

        return view('auth.platform-two-factor-setup', [
            'user' => $user,
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'setupSecret' => is_string($setupSecret) ? $setupSecret : null,
            'qrCode' => is_string($setupSecret)
                ? $this->twoFactor->qrCodeSvg($user, $setupSecret)
                : null,
            'recoveryCodes' => $request->session()->get(TwoFactorSession::FLASH_RECOVERY_CODES),
        ]);
    }

    public function begin(Request $request): RedirectResponse
    {
        $user = $this->resolvePlatformUser($request);

        if ($user === null) {
            return redirect()->route('platform.login');
        }

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('platform.two-factor.setup');
        }

        $request->session()->put(
            TwoFactorSession::SETUP_SECRET,
            $this->twoFactor->generateSecretKey(),
        );

        return redirect()
            ->route('platform.two-factor.setup')
            ->with('status', 'Skeniraj QR kôd i unesi potvrdni kôd iz autentifikatora.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->resolvePlatformUser($request);

        if ($user === null) {
            return redirect()->route('platform.login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $setupSecret = $request->session()->get(TwoFactorSession::SETUP_SECRET);

        if (! is_string($setupSecret) || $setupSecret === '') {
            return redirect()
                ->route('platform.two-factor.setup')
                ->with('warning', 'Prvo pokreni postavljanje 2FA.');
        }

        try {
            $recoveryCodes = $this->twoFactor->enable($user, $setupSecret, $validated['code']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        $request->session()->forget(TwoFactorSession::SETUP_SECRET);
        $request->session()->flash(TwoFactorSession::FLASH_RECOVERY_CODES, $recoveryCodes);

        return redirect()
            ->route('platform.two-factor.setup')
            ->with('status', 'Dvofaktorska autentifikacija je uključena.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->resolvePlatformUser($request);

        if ($user === null) {
            return redirect()->route('platform.login');
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        try {
            $this->twoFactor->disable($user, $validated['password'], $validated['code']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['disable' => $exception->getMessage()]);
        }

        $request->session()->forget(TwoFactorSession::SETUP_SECRET);

        return redirect()
            ->route('platform.two-factor.setup')
            ->with('status', 'Dvofaktorska autentifikacija je isključena.');
    }

    private function resolvePlatformUser(Request $request): ?\App\Models\User
    {
        if ($request->filled('token')) {
            $token = $request->string('token')->toString();
            $user = $this->platformAuth->resolveUserFromPlainToken($token);

            if ($user !== null && $user->isPlatformUser()) {
                $request->session()->put(PlatformLoginSession::TOKEN, $token);

                return $user;
            }
        }

        $token = $request->session()->get(PlatformLoginSession::TOKEN);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = $this->platformAuth->resolveUserFromPlainToken($token);

        return $user !== null && $user->isPlatformUser() ? $user : null;
    }
}
