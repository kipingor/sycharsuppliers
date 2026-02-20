<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\Statement\StatementBuilder;
use App\Services\Statement\StatementPdfGenerator;
use App\Services\Statement\StatementSender;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMonthlyStatements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        StatementBuilder $builder,
        StatementPdfGenerator $pdfGenerator,
        StatementSender $sender
    ): void {
        $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd   = now()->subMonthNoOverflow()->endOfMonth();

        Account::query()
            ->where('is_active', true)
            ->each(function (Account $account) use (
                $builder,
                $pdfGenerator,
                $sender,
                $periodStart,
                $periodEnd
            ) {
                // Idempotency guard (critical)
                if ($this->alreadySent($account, $periodStart)) {
                    return;
                }

                $statement = $builder->build($account, $periodStart, $periodEnd);
                $pdf = $pdfGenerator->generate($statement);

                $sender->send($statement, $pdf);

                $this->markAsSent($account, $periodStart);
            });
    }

    protected function alreadySent(Account $account, Carbon $period): bool
    {
        return cache()->has($this->cacheKey($account, $period));
    }

    protected function markAsSent(Account $account, Carbon $period): void
    {
        cache()->put(
            $this->cacheKey($account, $period),
            true,
            now()->addMonths(3)
        );
    }

    protected function cacheKey(Account $account, Carbon $period): string
    {
        return sprintf(
            'statement_sent:%s:%s',
            $account->id,
            $period->format('Y-m')
        );
    }
}