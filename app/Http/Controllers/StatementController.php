<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Statement\StatementBuilder;
use App\Services\Statement\StatementPdfGenerator;
use App\Services\Statement\StatementSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    public function download(
        Request $request,
        Account $account,
        StatementBuilder $builder,
        StatementPdfGenerator $pdfGenerator
    ): Response {
        $from = Carbon::parse($request->input('from'));
        $to   = Carbon::parse($request->input('to'));

        $statement = $builder->build($account, $from, $to);
        $pdf = $pdfGenerator->generate($statement);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement.pdf"',
        ]);
    }

    public function email(
        Request $request,
        Account $account,
        StatementBuilder $builder,
        StatementPdfGenerator $pdfGenerator,
        StatementSender $sender
    ): Response {
        $from = Carbon::parse($request->input('from'));
        $to   = Carbon::parse($request->input('to'));

        $statement = $builder->build($account, $from, $to);
        $pdf = $pdfGenerator->generate($statement);

        $sender->send($statement, $pdf);

        return response()->json([
            'message' => 'Statement emailed successfully',
        ]);
    }
}
