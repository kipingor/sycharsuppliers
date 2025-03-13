<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailLogRequest;
use App\Http\Requests\UpdateEmailLogRequest;
use App\Models\EmailLog;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
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
    public function store(StoreEmailLogRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmailLog $emailLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmailLog $emailLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmailLogRequest $request, EmailLog $emailLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmailLog $emailLog)
    {
        //
    }
}
