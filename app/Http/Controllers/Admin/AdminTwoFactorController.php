<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTwoFactorRequest;
use App\Http\Requests\Admin\DisableTwoFactorRequest;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Support\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminTwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $setupSecret = $request->session()->get(TwoFactorSession::SETUP_SECRET);
        $twoFactorRequired = config('security.require_super_admin_two_factor', true);

        return view('admin.security.index', [
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'twoFactorRequired' => $twoFactorRequired,
            'setupSecret' => is_string($setupSecret) ? $setupSecret : null,
            'qrCode' => is_string($setupSecret)
                ? $this->twoFactorAuthenticationService->qrCodeSvg($user, $setupSecret)
                : null,
            'recoveryCodes' => $request->session()->get(TwoFactorSession::FLASH_RECOVERY_CODES),
        ]);
    }

    public function beginSetup(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('admin.two-factor.index');
        }

        $request->session()->put(
            TwoFactorSession::SETUP_SECRET,
            $this->twoFactorAuthenticationService->generateSecretKey(),
        );

        return redirect()
            ->route('admin.two-factor.index')
            ->with('status', 'Skeniraj QR kôd i unesi potvrdni kôd iz autentifikatora.');
    }

    public function confirm(ConfirmTwoFactorRequest $request): RedirectResponse
    {
        $user = $request->user();
        $setupSecret = $request->session()->get(TwoFactorSession::SETUP_SECRET);

        if (! is_string($setupSecret) || $setupSecret === '') {
            return redirect()
                ->route('admin.two-factor.index')
                ->with('warning', 'Prvo pokreni postavljanje 2FA.');
        }

        try {
            $recoveryCodes = $this->twoFactorAuthenticationService->enable(
                $user,
                $setupSecret,
                $request->string('code')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'code' => $exception->getMessage(),
            ]);
        }

        $request->session()->forget(TwoFactorSession::SETUP_SECRET);
        $request->session()->flash(TwoFactorSession::FLASH_RECOVERY_CODES, $recoveryCodes);

        $redirect = config('security.require_super_admin_two_factor', true)
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.two-factor.index');

        return $redirect->with('status', 'Dvofaktorska autentifikacija je uključena.');
    }

    public function destroy(DisableTwoFactorRequest $request): RedirectResponse
    {
        if (config('security.require_super_admin_two_factor', true)) {
            abort(403, 'Dvofaktorska autentifikacija je obavezna i ne može se isključiti.');
        }

        $user = $request->user();

        try {
            $this->twoFactorAuthenticationService->disable(
                $user,
                $request->string('password')->toString(),
                $request->string('code')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'disable' => $exception->getMessage(),
            ]);
        }

        $request->session()->forget(TwoFactorSession::SETUP_SECRET);

        return redirect()
            ->route('admin.two-factor.index')
            ->with('status', 'Dvofaktorska autentifikacija je isključena.');
    }
}
