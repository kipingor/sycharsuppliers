<?php

namespace App\Services\Statement;

use Barryvdh\DomPDF\Facade\Pdf;

class StatementPdfGenerator
{
    public function generate(array $statementData): string
    {
        return Pdf::loadView('pdf.statement', [
            'statement' => $statementData,
        ])->output();
    }
}
