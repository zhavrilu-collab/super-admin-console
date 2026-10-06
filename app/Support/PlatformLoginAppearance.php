<?php

namespace App\Support;

use App\Models\Application;

final class PlatformLoginAppearance
{
    /**
     * @return array{
     *     theme: string,
     *     brand: string,
     *     document_title: string,
     *     heading: string,
     *     tagline: ?string,
     *     intro: ?string,
     *     lockup: ?string,
     *     lockup_alt: string,
     *     register_url: ?string,
     *     register_prompt: ?string,
     *     register_cta: string,
     *     forgot_password_url: string,
     *     button_label: string,
     *     show_app_badge: bool,
     * }
     */
    public static function for(?string $slug, ?Application $application = null): array
    {
        $base = rtrim((string) ($application?->api_base_url ?? ''), '/');
        $forgotPasswordUrl = url('/zaboravljena-lozinka');

        $defaults = [
            'theme' => 'platform',
            'brand' => 'Platforma',
            'document_title' => 'Prijava — Platforma',
            'heading' => 'Prijava',
            'tagline' => 'Jedinstvena prijava za sve aplikacije',
            'intro' => null,
            'lockup' => null,
            'lockup_alt' => 'Platforma',
            'register_url' => null,
            'register_prompt' => null,
            'register_cta' => 'Registrirajte novu',
            'forgot_password_url' => $forgotPasswordUrl,
            'button_label' => 'Prijavi se',
            'show_app_badge' => $application !== null,
        ];

        return match ($slug) {
            'hr-saas' => [
                ...$defaults,
                'theme' => 'hr',
                'brand' => 'SuperSkyCrew',
                'document_title' => 'Prijava — SuperSkyCrew',
                'tagline' => null,
                'intro' => 'Prijava u SuperSkyCrew — platformu za upravljanje ljudskim resursima.',
                'lockup' => 'brand/superskycrew-zelena.png',
                'lockup_alt' => 'SuperSkyCrew',
                'register_url' => $base !== '' ? $base.'/registracija' : null,
                'register_prompt' => 'Nemate tvrtku?',
                'show_app_badge' => false,
            ],
            'udruga-saas' => [
                ...$defaults,
                'theme' => 'udruga',
                'brand' => 'SuperSkyClub',
                'document_title' => 'Prijava — SuperSkyClub',
                'tagline' => null,
                'intro' => 'Prijava putem centralne platforme.',
                'lockup' => 'brand/superskyclub-zelena.png',
                'lockup_alt' => 'SuperSkyClub',
                'register_url' => $base !== '' ? $base.'/registracija' : null,
                'register_prompt' => 'Nemate udrugu?',
                'show_app_badge' => false,
            ],
            'legal-saas' => [
                ...$defaults,
                'theme' => 'law',
                'brand' => 'SuperSkyLaw',
                'document_title' => 'Prijava — SuperSkyLaw',
                'tagline' => null,
                'intro' => 'Prijava u SuperSkyLaw.',
                'lockup' => 'brand/superskylaw-zelena.png',
                'lockup_alt' => 'SuperSkyLaw',
                'register_url' => $base !== '' ? $base.'/registracija' : null,
                'register_prompt' => 'Nemate ured?',
                'show_app_badge' => false,
            ],
            default => $defaults,
        };
    }
}
