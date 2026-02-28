<?php

namespace App\Mail;

use App\Models\Billing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingStatement extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $pdf   Raw PDF binary from Pdf::loadView(...)->output()
     * @param Billing $billing
     */
    public function __construct(
        public string $pdf,
        public Billing $billing,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address('sales@sycharsuppliers.com', 'Sychar Suppliers'),
            subject: "Bill Statement #{$this->billing->id} — {$this->billing->getFormattedPeriod()}",
        );
    }

    public function content(): Content
    {
        // details is a HasMany *collection* — load meters eagerly to avoid N+1
        $details = $this->billing->details->map(function ($detail) {
            return [
                'meter_number'     => $detail->meter?->meter_number ?? 'N/A',
                'meter_name'       => $detail->meter?->meter_name   ?? null,
                'previous_reading' => $detail->previous_reading,
                'current_reading'  => $detail->current_reading,
                'units'            => $detail->units,
                'rate'             => $detail->rate,
                'amount'           => $detail->amount,
                'description'      => $detail->description ?? null,
            ];
        });

        return new Content(
            markdown: 'emails.billing_statement',
            with: [
                'account'       => $this->billing->account,
                'billing'       => $this->billing,
                'details'       => $details,          // collection of arrays, not models
                'total_billed'  => $this->billing->total_amount,
                'total_paid'    => $this->billing->paid_amount,
                'balance_due'   => $this->billing->balance,
                'is_overdue'    => $this->billing->isOverdue(),
                'due_date'      => $this->billing->due_date->format('d M Y'),
                'billing_period'=> $this->billing->getFormattedPeriod(),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            // Attach the PDF binary directly — no temp file needed
            Attachment::fromData(
                fn () => $this->pdf,
                "bill_statement_{$this->billing->id}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}