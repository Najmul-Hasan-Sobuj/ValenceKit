<?php

namespace Database\Seeders;

use App\Models\Proposal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProposalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Proposal demo data is only available in local and testing environments.');

            return;
        }

        $today = today();
        $sequence = 0;
        foreach (['proposal', 'quotation', 'contract'] as $type) {
            foreach (['draft', 'sent', 'accepted', 'rejected', 'expired'] as $status) {
                foreach (['BDT', 'USD'] as $currency) {
                    $sequence++;
                    $itemCount = $sequence % 3 === 0 ? 12 : ($sequence % 2 === 0 ? 3 : 1);
                    $items = [];
                    for ($item = 1; $item <= $itemCount; $item++) {
                        $items[] = [
                            'description' => $item === 1
                                ? 'Discovery, requirements analysis and project planning / প্রজেক্ট পরিকল্পনা'
                                : 'Service deliverable '.$item.' — design, implementation, review and handover',
                            'quantity' => $item % 2 === 0 ? 2.5 : 1,
                            'unit_price_minor' => $sequence === 1 ? 0 : 125050 + ($item * 10000),
                        ];
                    }

                    $subtotal = 0;
                    foreach ($items as $line) {
                        $subtotal += (int) round($line['quantity'] * $line['unit_price_minor']);
                    }
                    $discount = $sequence % 4 === 0 ? $subtotal : ($sequence % 2 === 0 ? (int) round($subtotal * 0.1) : 0);
                    $taxRate = $sequence % 3 === 0 ? 1500 : 0;
                    $tax = (int) round(($subtotal - $discount) * $taxRate / 10000);
                    $hasResponse = in_array($status, ['accepted', 'rejected'], true);

                    Proposal::firstOrCreate(['number' => sprintf('DEMO-%s-%03d', strtoupper($type), $sequence)], [
                        'public_id' => (string) Str::uuid(),
                        'type' => $type,
                        'status' => $status === 'expired' ? 'sent' : $status,
                        'title' => '[Demo] '.ucfirst($status).' '.ucfirst($type).' — '.($itemCount === 12 ? 'Full-service website, branding, integrations, migration and long-form delivery scope for responsive layout testing' : 'Website delivery package'),
                        'client_name' => $currency === 'BDT' ? 'নমুনা ডিজিটাল এজেন্সি '.$sequence : 'Example Creative Studio '.$sequence,
                        'client_email' => 'proposal-demo-'.$sequence.'@example.test',
                        'currency' => $currency,
                        'items' => $items,
                        'subtotal_minor' => $subtotal,
                        'discount_minor' => $discount,
                        'tax_rate_basis_points' => $taxRate,
                        'tax_minor' => $tax,
                        'total_minor' => $subtotal - $discount + $tax,
                        'issued_on' => $today->copy()->subDays(14),
                        'valid_until' => $status === 'expired' ? $today->copy()->subDay() : ($sequence % 2 === 0 ? $today->copy() : $today->copy()->addDays(14)),
                        'terms' => "Demo terms only.\n50% deposit before work starts.\nTwo revision rounds included.\nঅতিরিক্ত কাজের জন্য পৃথক অনুমোদন প্রয়োজন।",
                        'notes' => "Synthetic manual-testing data. No real client or verified signature.\n".($status === 'sent' ? 'Change the recipient email to an inbox you control before requesting a verification code.' : 'Use this document to check filters, status, layouts and protected actions.'),
                        'signer_name' => $status === 'accepted' ? 'Demo Signer (sample only)' : null,
                        'response_note' => $hasResponse ? ($status === 'accepted' ? 'Sample approval for display testing; not an email-verified signature.' : 'Sample rejection: please revise the scope and delivery schedule.') : null,
                        'responded_at' => $hasResponse ? now()->subDays(2) : null,
                    ]);
                }
            }
        }
    }
}
