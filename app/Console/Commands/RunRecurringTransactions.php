<?php

namespace App\Console\Commands;

use App\Models\FinanceTransaction;
use App\Services\AuditLogService;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('finance:run-recurring {--dry-run : Report due occurrences without writing}')]
#[Description('Generate due recurring transactions, once per scheduled occurrence')]
class RunRecurringTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FinanceService $finance, AuditLogService $audit): int
    {
        $count = 0;
        $today = CarbonImmutable::today('Asia/Dhaka');
        FinanceTransaction::where('is_recurring', true)->whereNull('recurring_parent_id')->chunkById(100, function ($parents) use ($finance, $audit, $today, &$count): void {
            foreach ($parents as $parent) {
                DB::transaction(function () use ($parent, $finance, $audit, $today, &$count): void {
                    $parent = FinanceTransaction::lockForUpdate()->find($parent->id);
                    if (! $parent?->is_recurring || ! in_array($parent->recurring_frequency, ['weekly', 'monthly', 'yearly'], true)
                        || ! $parent->admin?->is_active || ! $parent->admin->can('finance.create')
                        || ! $finance->findActiveCategory($parent->category_key, $parent->type)) {
                        return;
                    }
                    $anchor = CarbonImmutable::parse($parent->transaction_date->toDateString(), 'Asia/Dhaka');
                    for ($batch = 0; $batch < 120; $batch++) {
                        $occurrence = $parent->recurring_occurrence + 1;
                        $next = match ($parent->recurring_frequency) {
                            'weekly' => $anchor->addWeeks($occurrence),
                            'monthly' => $anchor->addMonthsNoOverflow($occurrence),
                            'yearly' => $anchor->addYearsNoOverflow($occurrence),
                        };
                        if ($next->gt($today)) {
                            if (! $this->option('dry-run')) {
                                $parent->update(['recurring_next_on' => $next->toDateString()]);
                            }
                            break;
                        }
                        if (! $this->option('dry-run')) {
                            $generated = FinanceTransaction::firstOrCreate([
                                'recurring_parent_id' => $parent->id, 'recurrence_on' => $next->toDateString(),
                            ], [...$parent->only(['type', 'amount', 'currency', 'category_key', 'category_name', 'description', 'payment_method', 'notes', 'admin_id']),
                                'transaction_date' => $next->toDateString(), 'is_recurring' => false,
                                'reference' => FinanceTransaction::generateReference(),
                            ]);
                            if ($generated->wasRecentlyCreated) {
                                $audit->created('finance.recurring.generated', $generated, metadata: ['parent_id' => $parent->id], actor: $parent->admin);
                            }
                            $parent->update(['recurring_occurrence' => $occurrence]);
                        } else {
                            $parent->recurring_occurrence = $occurrence;
                        }
                        $count++;
                    }
                });
            }
        });
        $this->info($count.' recurring occurrences '.($this->option('dry-run') ? 'due.' : 'processed.'));

        return self::SUCCESS;
    }
}
