<?php

namespace App\Services\Admin;

use App\Enums\ApplicationFeatureType;
use App\Models\Application;
use App\Models\ApplicationFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ApplicationFeatureCatalogService
{
    /**
     * @return Collection<int, ApplicationFeature>
     */
    public function forApplication(?int $applicationId): Collection
    {
        if ($applicationId === null) {
            return new Collection;
        }

        return ApplicationFeature::query()
            ->where('application_id', $applicationId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function seedDefaults(Application $application): void
    {
        $defaults = $this->isSmbApplication($application)
            ? $this->smbCatalogDefinitions()
            : $this->udrugaCatalogDefinitions();

        foreach ($defaults as $feature) {
            ApplicationFeature::query()->updateOrCreate(
                [
                    'application_id' => $application->id,
                    'key' => $feature['key'],
                ],
                [
                    'label' => $feature['label'],
                    'description' => $feature['description'] ?? null,
                    'type' => $feature['type'],
                    'unit' => $feature['unit'] ?? null,
                    'sort_order' => $feature['sort_order'],
                ],
            );
        }

        $this->mergeMissingFeaturesIntoPlans($application);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFeaturesForPlanSlug(Application $application, string $slug): array
    {
        if ($this->isSmbApplication($application)) {
            return $this->smbPlanFeatureDefaults($slug);
        }

        return $this->udrugaPlanFeatureDefaults($slug);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeFeaturesPayload(?int $applicationId, array $input): array
    {
        $catalog = $this->forApplication($applicationId);
        $normalized = [];

        foreach ($catalog as $feature) {
            $raw = $input[$feature->key] ?? null;

            if ($feature->isLimit()) {
                $normalized[$feature->key] = ($raw === null || $raw === '')
                    ? null
                    : (int) $raw;

                continue;
            }

            $normalized[$feature->key] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    public function assertDeletable(ApplicationFeature $feature): void
    {
        $application = $feature->application;
        $builtinKeys = collect(
            $this->isSmbApplication($application)
                ? $this->smbCatalogDefinitions()
                : $this->udrugaCatalogDefinitions()
        )->pluck('key')->all();

        if (in_array($feature->key, $builtinKeys, true)) {
            throw ValidationException::withMessages([
                'key' => ['Ugrađene značajke se ne mogu obrisati, samo urediti naziv/opis.'],
            ]);
        }
    }

    public function mergeMissingFeaturesIntoPlans(Application $application): void
    {
        $plans = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->get();

        foreach ($plans as $plan) {
            $defaults = $this->defaultFeaturesForPlanSlug($application, $plan->slug);
            $current = is_array($plan->features) ? $plan->features : [];
            $merged = $current;

            foreach ($defaults as $key => $value) {
                if (! array_key_exists($key, $merged)) {
                    $merged[$key] = $value;
                }
            }

            if ($merged === $current) {
                continue;
            }

            $plan->forceFill(['features' => $merged])->save();
        }
    }

    private function isSmbApplication(Application $application): bool
    {
        return str_contains(strtolower($application->slug), 'smb');
    }

    /**
     * @return list<array{key: string, label: string, description?: string|null, type: ApplicationFeatureType, unit?: string|null, sort_order: int}>
     */
    public function udrugaCatalogDefinitions(): array
    {
        $B = ApplicationFeatureType::Boolean;
        $L = ApplicationFeatureType::Limit;

        return [
            // Limiti
            ['key' => 'member_limit', 'label' => 'Limit članova', 'description' => 'Maksimalan broj aktivnih članova', 'type' => $L, 'unit' => 'members', 'sort_order' => 10],
            ['key' => 'newsletter_limit', 'label' => 'Limit newslettera / mjesec', 'type' => $L, 'unit' => 'newsletters', 'sort_order' => 11],
            ['key' => 'outbound_email_limit', 'label' => 'Limit poslanih mailova / mjesec', 'type' => $L, 'unit' => 'emails', 'sort_order' => 12],
            ['key' => 'event_limit', 'label' => 'Limit događaja / godina', 'type' => $L, 'unit' => 'events', 'sort_order' => 13],

            // Javni web
            ['key' => 'public_site', 'label' => 'Javni web modul', 'type' => $B, 'sort_order' => 20],
            ['key' => 'subdomain', 'label' => 'Poddomena', 'type' => $B, 'sort_order' => 21],
            ['key' => 'custom_domain', 'label' => 'Vlastita domena', 'type' => $B, 'sort_order' => 22],
            ['key' => 'editable_sections', 'label' => 'Uređivanje sekcija javnog weba', 'type' => $B, 'sort_order' => 23],
            ['key' => 'cookie_banner', 'label' => 'Cookie banner', 'type' => $B, 'sort_order' => 24],
            ['key' => 'web_posts', 'label' => 'Objave / vijesti', 'type' => $B, 'sort_order' => 25],
            ['key' => 'web_custom_theme', 'label' => 'Prilagođene teme i boje', 'type' => $B, 'sort_order' => 26],

            // Članstvo
            ['key' => 'member_groups', 'label' => 'Grupe članova', 'type' => $B, 'sort_order' => 30],
            ['key' => 'membership_charges', 'label' => 'Obračuni / godišnja članarina', 'type' => $B, 'sort_order' => 31],
            ['key' => 'payment_slips', 'label' => 'HUB3 uplatnice / QR', 'type' => $B, 'sort_order' => 32],
            ['key' => 'membership_reminders', 'label' => 'Mail podsjetnici na uplatu', 'type' => $B, 'sort_order' => 33],
            ['key' => 'member_export', 'label' => 'Izvoz članova (XLS/PDF)', 'type' => $B, 'sort_order' => 34],
            ['key' => 'paper_applications', 'label' => 'Papirnate pristupnice', 'type' => $B, 'sort_order' => 35],
            ['key' => 'application_form_builder', 'label' => 'Konfigurator pristupnica', 'type' => $B, 'sort_order' => 36],
            ['key' => 'membership_categories', 'label' => 'Kategorije članstva', 'type' => $B, 'sort_order' => 37],
            ['key' => 'roles_bodies', 'label' => 'Uloge i tijela', 'type' => $B, 'sort_order' => 38],

            // Komunikacija
            ['key' => 'email_templates', 'label' => 'Uređivanje predložaka mailova', 'type' => $B, 'sort_order' => 40],
            ['key' => 'custom_smtp', 'label' => 'Vlastiti SMTP', 'type' => $B, 'sort_order' => 41],
            ['key' => 'meeting_invitations', 'label' => 'Pozivi na sjednice', 'type' => $B, 'sort_order' => 42],
            ['key' => 'newsletters', 'label' => 'Newsletter / obavijesti', 'type' => $B, 'sort_order' => 43],
            ['key' => 'whatsapp_link', 'label' => 'WhatsApp link', 'type' => $B, 'sort_order' => 44],
            ['key' => 'social_links', 'label' => 'Društvene mreže', 'type' => $B, 'sort_order' => 45],

            // Događaji
            ['key' => 'events', 'label' => 'Modul događaja', 'type' => $B, 'sort_order' => 50],
            ['key' => 'event_guests', 'label' => 'Katalog uzvanika', 'type' => $B, 'sort_order' => 51],
            ['key' => 'event_invitations', 'label' => 'Pozivnice uzvanicima (RSVP)', 'type' => $B, 'sort_order' => 52],
            ['key' => 'event_documents', 'label' => 'Dokumenti događaja', 'type' => $B, 'sort_order' => 53],
            ['key' => 'event_work_groups', 'label' => 'Radne skupine', 'type' => $B, 'sort_order' => 54],
            ['key' => 'event_donations', 'label' => 'Donacije na događaju', 'type' => $B, 'sort_order' => 55],
            ['key' => 'event_minutes', 'label' => 'Zapisnici događaja', 'type' => $B, 'sort_order' => 56],

            // Financije
            ['key' => 'finance', 'label' => 'Financijski modul', 'type' => $B, 'sort_order' => 60],
            ['key' => 'finance_invoices', 'label' => 'Ulazni / izlazni računi', 'type' => $B, 'sort_order' => 61],
            ['key' => 'finance_kpi', 'label' => 'Primitci / izdatci (KPI)', 'type' => $B, 'sort_order' => 62],
            ['key' => 'finance_cashbook', 'label' => 'Blagajna', 'type' => $B, 'sort_order' => 63],
            ['key' => 'finance_travel_orders', 'label' => 'Putni nalozi', 'type' => $B, 'sort_order' => 64],
            ['key' => 'finance_ledger', 'label' => 'Glavna knjiga', 'type' => $B, 'sort_order' => 65],
            ['key' => 'finance_projects', 'label' => 'Projekti', 'type' => $B, 'sort_order' => 66],
            ['key' => 'finance_assets', 'label' => 'Osnovna sredstva', 'type' => $B, 'sort_order' => 67],
            ['key' => 'finance_reports', 'label' => 'Financijski izvještaji', 'type' => $B, 'sort_order' => 68],
            ['key' => 'finance_catalog', 'label' => 'Artikli / klasifikacija', 'type' => $B, 'sort_order' => 69],

            // Inventar i dokumenti
            ['key' => 'inventory', 'label' => 'Modul inventara', 'type' => $B, 'sort_order' => 70],
            ['key' => 'inventory_item_limit', 'label' => 'Limit stavki inventara', 'type' => $L, 'unit' => 'items', 'sort_order' => 71],
            ['key' => 'documents', 'label' => 'Modul dokumenata', 'type' => $B, 'sort_order' => 72],
            ['key' => 'documents_storage_mb', 'label' => 'Prostor za dokumente', 'type' => $L, 'unit' => 'MB', 'sort_order' => 73],
            ['key' => 'documents_custom_templates_limit', 'label' => 'Limit predložaka dokumenata', 'type' => $L, 'unit' => 'templates', 'sort_order' => 74],
            ['key' => 'member_portal', 'label' => 'Članski portal', 'type' => $B, 'sort_order' => 75],
            ['key' => 'membership_card', 'label' => 'Članska iskaznica', 'type' => $B, 'sort_order' => 76],
            ['key' => 'sport_addon', 'label' => 'Sportski dodatak', 'type' => $B, 'sort_order' => 77],

            // Podaci / admin
            ['key' => 'data_backup', 'label' => 'Sigurnosna kopija', 'type' => $B, 'sort_order' => 80],
            ['key' => 'data_export', 'label' => 'Izvoz podataka', 'type' => $B, 'sort_order' => 81],
            ['key' => 'data_import', 'label' => 'Uvoz podataka', 'type' => $B, 'sort_order' => 82],
            ['key' => 'staff_invites', 'label' => 'Pozivnice administratorima', 'type' => $B, 'sort_order' => 83],
            ['key' => 'audit_log', 'label' => 'Zapisnik promjena', 'type' => $B, 'sort_order' => 84],
            ['key' => 'rbac_custom', 'label' => 'Prilagođena prava po ulogama', 'type' => $B, 'sort_order' => 85],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description?: string|null, type: ApplicationFeatureType, unit?: string|null, sort_order: int}>
     */
    public function smbCatalogDefinitions(): array
    {
        $B = ApplicationFeatureType::Boolean;
        $L = ApplicationFeatureType::Limit;

        return [
            ['key' => 'member_limit', 'label' => 'Limit članova tima', 'type' => $L, 'unit' => 'members', 'sort_order' => 10],
            ['key' => 'team_management', 'label' => 'Upravljanje timom / pozivnice', 'type' => $B, 'sort_order' => 20],
            ['key' => 'sales_module', 'label' => 'Modul Prodaja', 'type' => $B, 'sort_order' => 30],
            ['key' => 'finance_module', 'label' => 'Modul Financije', 'type' => $B, 'sort_order' => 40],
            ['key' => 'subdomain', 'label' => 'Poddomena', 'type' => $B, 'sort_order' => 50],
            ['key' => 'custom_domain', 'label' => 'Vlastita domena', 'type' => $B, 'sort_order' => 60],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function udrugaPlanFeatureDefaults(string $slug): array
    {
        $premium = [
            'member_limit' => null,
            'newsletter_limit' => null,
            'outbound_email_limit' => null,
            'event_limit' => null,
            'public_site' => true,
            'subdomain' => true,
            'custom_domain' => true,
            'editable_sections' => true,
            'cookie_banner' => true,
            'web_posts' => true,
            'web_custom_theme' => true,
            'member_groups' => true,
            'membership_charges' => true,
            'payment_slips' => true,
            'membership_reminders' => true,
            'member_export' => true,
            'paper_applications' => true,
            'application_form_builder' => true,
            'membership_categories' => true,
            'roles_bodies' => true,
            'email_templates' => true,
            'custom_smtp' => true,
            'meeting_invitations' => true,
            'newsletters' => true,
            'whatsapp_link' => true,
            'social_links' => true,
            'events' => true,
            'event_guests' => true,
            'event_invitations' => true,
            'event_documents' => true,
            'event_work_groups' => true,
            'event_donations' => true,
            'event_minutes' => true,
            'finance' => true,
            'finance_invoices' => true,
            'finance_kpi' => true,
            'finance_cashbook' => true,
            'finance_travel_orders' => true,
            'finance_ledger' => true,
            'finance_projects' => true,
            'finance_assets' => true,
            'finance_reports' => true,
            'finance_catalog' => true,
            'inventory' => true,
            'inventory_item_limit' => null,
            'documents' => true,
            'documents_storage_mb' => null,
            'documents_custom_templates_limit' => null,
            'member_portal' => true,
            'membership_card' => true,
            'sport_addon' => true,
            'data_backup' => true,
            'data_export' => true,
            'data_import' => true,
            'staff_invites' => true,
            'audit_log' => true,
            'rbac_custom' => true,
        ];

        return match ($slug) {
            'basic' => array_merge($premium, [
                'member_limit' => 50,
                'newsletter_limit' => 2,
                'outbound_email_limit' => 100,
                'event_limit' => 5,
                'subdomain' => false,
                'custom_domain' => false,
                'editable_sections' => false,
                'cookie_banner' => false,
                'web_posts' => false,
                'web_custom_theme' => false,
                'member_groups' => false,
                'membership_charges' => true,
                'payment_slips' => false,
                'membership_reminders' => false,
                'member_export' => false,
                'paper_applications' => true,
                'application_form_builder' => false,
                'email_templates' => false,
                'custom_smtp' => false,
                'meeting_invitations' => false,
                'newsletters' => false,
                'whatsapp_link' => false,
                'social_links' => false,
                'events' => true,
                'event_guests' => false,
                'event_invitations' => false,
                'event_documents' => false,
                'event_work_groups' => false,
                'event_donations' => false,
                'event_minutes' => false,
                'finance' => false,
                'finance_invoices' => false,
                'finance_kpi' => false,
                'finance_projects' => false,
                'finance_assets' => false,
                'finance_reports' => false,
                'finance_catalog' => false,
                'data_backup' => false,
                'data_export' => false,
                'data_import' => false,
                'staff_invites' => true,
                'audit_log' => false,
                'rbac_custom' => false,
            ]),
            'standard' => array_merge($premium, [
                'member_limit' => 500,
                'newsletter_limit' => 20,
                'outbound_email_limit' => 2000,
                'event_limit' => 50,
                'custom_domain' => false,
                'web_custom_theme' => false,
                'custom_smtp' => false,
                'finance_assets' => false,
                'finance_projects' => false,
                'data_import' => false,
                'rbac_custom' => false,
            ]),
            default => $premium,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function smbPlanFeatureDefaults(string $slug): array
    {
        $premium = [
            'member_limit' => null,
            'team_management' => true,
            'sales_module' => true,
            'finance_module' => true,
            'subdomain' => true,
            'custom_domain' => true,
        ];

        return match ($slug) {
            'basic' => [
                'member_limit' => 3,
                'team_management' => true,
                'sales_module' => false,
                'finance_module' => false,
                'subdomain' => false,
                'custom_domain' => false,
            ],
            'standard' => [
                'member_limit' => 15,
                'team_management' => true,
                'sales_module' => true,
                'finance_module' => false,
                'subdomain' => true,
                'custom_domain' => false,
            ],
            default => $premium,
        };
    }
}
