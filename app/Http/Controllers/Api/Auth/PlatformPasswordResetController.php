<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AppPasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformPasswordResetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'application_slug' => ['required', 'string'],
        ], [
            'email.required' => 'Unesite e-mail adresu.',
            'email.email' => 'E-mail adresa nije ispravna.',
            'application_slug.required' => 'Aplikacija nije prepoznata.',
        ]);

        $base = $this->resetBaseUrl($data['application_slug']);

        $previousLocale = App::getLocale();
        App::setLocale('hr');

        try {
            $status = Password::sendResetLink(
                ['email' => $data['email']],
                function (User $user, string $token) use ($base): void {
                    $user->notify(new AppPasswordResetNotification($token, $base));
                },
            );
            $message = __($status);
        } finally {
            App::setLocale($previousLocale);
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Unesite e-mail adresu.',
            'email.email' => 'E-mail adresa nije ispravna.',
            'password.required' => 'Unesite novu lozinku.',
            'password.min' => 'Lozinka mora imati najmanje 8 znakova.',
            'password.confirmed' => 'Potvrda lozinke se ne podudara.',
        ]);

        $previousLocale = App::getLocale();
        App::setLocale('hr');

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user) use ($request): void {
                    $user->forceFill([
                        'password' => Hash::make($request->string('password')->toString()),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );
            $message = __($status);
        } finally {
            App::setLocale($previousLocale);
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
        ], $status === Password::PASSWORD_RESET ? 200 : 422);
    }

    private function resetBaseUrl(string $slug): string
    {
        $configured = config('saas_applications.applications.'.$slug.'.base_url');
        $base = is_string($configured) ? rtrim($configured, '/') : '';

        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'application_slug' => ['Aplikacija nije prepoznata.'],
            ]);
        }

        return $base;
    }
}
