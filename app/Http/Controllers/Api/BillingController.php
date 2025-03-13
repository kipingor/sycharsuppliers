<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Meter;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index()
    {
        return Billing::with('Meter')->paginate(10);
    }

    public function markPaid($id)
    {
        $bill = Billing::findOrFail($id);
        $bill->markAsPaid();

        return response()->json(['message' => 'Bill marked as paid']);
    }

    public function applyLateFees()
    {
        $overdueBills = Billing::where('status', 'pending')->get();
        foreach ($overdueBills as $bill) {
            $bill->applyLateFee();
        }

        return response()->json(['message' => 'Late fees applied']);
    }
}
