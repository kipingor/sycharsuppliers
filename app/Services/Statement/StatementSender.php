<?php

namespace App\Services\Statement;

use App\Mail\AccountStatementMail;
use Illuminate\Support\Facades\Mail;

class StatementSender
{
    public function send(array $statementData, string $pdf): void
    {
        Mail::to($statementData['account']['email'])->send(
            new AccountStatementMail($pdf, $statementData)
        );
    }
}