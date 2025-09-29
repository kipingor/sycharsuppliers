<?php

namespace App\Mail;

use App\Models\Resident;
use App\Models\Meter;
use App\Models\Billing;
use App\Models\BillingDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use MailerSend\Helpers\Builder\Variable;
use MailerSend\Helpers\Builder\Personalization;
use MailerSend\LaravelDriver\MailerSendTrait;


class BillingStatement extends Mailable
{
    use Queueable, SerializesModels, MailerSendTrait;    

    public $total_billed;
    public $total_paid;
    public $balance_due;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Resident $resident, 
        public Meter $meter, 
        public Billing $billing, 
        public BillingDetail $details, 
        $total_billed, 
        $total_paid, 
        $balance_due
        )
    {        
        $this->total_billed = $total_billed;
        $this->total_paid = $total_paid;
        $this->balance_due = $balance_due;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('sales@sycharsuppliers.com', 'Sychar Suppliers'),
            subject: 'Billing Statement',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {

        return new Content(
            markdown: 'emails.billing_statement',
            with: [
                'resident' => $this->resident,
                'meter' => $this->meter,
                'billing' => $this->billing,
                'details' => $this->details,
                'total_billed' => $this->total_billed,
                'total_paid' => $this->total_paid,
                'balance_due' => $this->balance_due,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
