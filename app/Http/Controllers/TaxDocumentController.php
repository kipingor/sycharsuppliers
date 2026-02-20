<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxDocumentRequest;
use App\Http\Requests\UpdateTaxDocumentRequest;
use App\Models\TaxDocument;
use Inertia\Inertia;
use Inertia\Response;

class TaxDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxDocumentRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxDocument $taxDocument)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxDocument $taxDocument)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxDocumentRequest $request, TaxDocument $taxDocument)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxDocument $taxDocument)
    {
        //
    }

    public function generateTaxDocument($type, $period_start, $period_end)
    {
        $total_income = \App\Models\Billing::whereBetween('created_at', [$period_start, $period_end])
            ->sum('total_amount');
        $total_expenses = \App\Models\Expense::whereBetween('expense_date', [$period_start, $period_end])->sum('amount');
        $taxable_amount = $total_income - $total_expenses;

        $taxDocument = TaxDocument::create([
            'type' => $type,
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'taxable_amount' => $taxable_amount,
            'period_start' => $period_start,
            'period_end' => $period_end,
        ]);

        return $taxDocument;
    }
}
