<?php

namespace App\Console\Commands;

use App\Models\MailDelivery;
use App\Services\MailDeliveryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:recover-pending')]
#[Description('Recover committed mail that could not be dispatched to the queue')]
class RecoverPendingMail extends Command
{
    public function handle(MailDeliveryService $delivery): int
    {
        MailDelivery::where('status', 'pending')->whereNotNull('notification')->where('updated_at', '<', now()->subMinutes(2))
            ->chunkById(100, function ($records) use ($delivery): void {
                foreach ($records as $record) {
                    $delivery->dispatch($record);
                }
            });

        return self::SUCCESS;
    }
}
