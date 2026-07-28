<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Admin\ConsoleSettingsService;

class BillingBankTransferService
{
    public function __construct(
        private readonly ConsoleSettingsService $consoleSettings,
    ) {}

    /**
     * @return array{
     *     reference: string,
     *     reference_display: string,
     *     purpose: string,
     *     recipient_name: string,
     *     iban: string|null,
     *     amount_cents: int|null,
     *     amount_formatted: string|null,
     *     amount_parts: array{integer: string, decimal: string}|null,
     *     currency: string,
     *     payment_days: int,
     *     due_date: string,
     *     plan_name: string,
     *     plan_slug: string,
     * }
     */
    public function createPaymentInstructions(
        Tenant $tenant,
        SubscriptionPlan $plan,
        ?string $payerOib = null,
    ): array {
        if (! $this->consoleSettings->bankTransferEnabled()) {
            throw new \InvalidArgumentException('Uplata na račun trenutačno nije dostupna.');
        }

        $iban = $this->consoleSettings->bankTransferIban();
        if ($iban === null) {
            throw new \InvalidArgumentException(
                'IBAN za uplate nije konfiguriran na platformi. U konzoli otvorite Postavke i unesite IBAN za uplatu na račun.'
            );
        }

        $oib = preg_replace('/\D+/', '', (string) $payerOib) ?: '00000000000';
        $oib = str_pad(substr($oib, 0, 11), 11, '0', STR_PAD_LEFT);
        $planCode = $this->planCode($plan->slug);
        $broj = now()->format('ymd');
        $datum = now()->timezone(config('app.timezone'))->format('d.m.Y.');

        // HUB3A poziv na broj: max 22 znaka → OIB(11)-PAK(3)-broj(6) = 22
        $reference = "{$oib}-{$planCode}-{$broj}";
        $referenceDisplay = "{$oib}-{$planCode}-{$broj} ({$datum})";
        $purpose = mb_substr('Pretplata '.$plan->name.' '.$datum, 0, 35);

        $amountCents = $plan->monthly_price_cents;
        $currency = strtoupper((string) config('billing.currency', 'eur'));
        $paymentDays = $this->consoleSettings->bankTransferPaymentDays();

        $amountParts = null;
        if (is_int($amountCents) && $amountCents > 0) {
            $amountParts = [
                'integer' => number_format(intdiv($amountCents, 100), 0, '', ''),
                'decimal' => str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT),
            ];
        }

        return [
            'reference' => $reference,
            'reference_display' => $referenceDisplay,
            'purpose' => $purpose,
            'recipient_name' => $this->consoleSettings->bankTransferRecipient(),
            'iban' => $iban,
            'amount_cents' => is_int($amountCents) && $amountCents > 0 ? $amountCents : null,
            'amount_formatted' => is_int($amountCents) && $amountCents > 0
                ? number_format($amountCents / 100, 2, ',', '.').' '.$currency
                : null,
            'amount_parts' => $amountParts,
            'currency' => $currency,
            'payment_days' => $paymentDays,
            'due_date' => now()->addDays($paymentDays)->timezone(config('app.timezone'))->format('d.m.Y.'),
            'plan_name' => $plan->name,
            'plan_slug' => $plan->slug,
        ];
    }

    private function planCode(string $slug): string
    {
        return match (strtolower($slug)) {
            'basic', 'osnovni' => 'BAS',
            'standard', 'standardni' => 'STD',
            'premium', 'napredni' => 'PRE',
            default => strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $slug) ?: 'PKG', 0, 3)),
        };
    }
}
