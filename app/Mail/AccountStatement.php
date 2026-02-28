<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountStatement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $pdf,
        public Account $account,
        public array   $data,       // the statement data array from AccountStatementGenerator
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address(
                config('mail.from.address', 'sales@sycharsuppliers.com'),
                config('mail.from.name', 'Sychar Suppliers')
            ),
            subject: "Account Statement — {$this->account->name} ({$this->data['period']['start']} to {$this->data['period']['end']})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account_statement',
            with: [
                'account'         => $this->data['account'],
                'period'          => $this->data['period'],
                'opening_balance' => $this->data['opening_balance'],
                'total_billed'    => $this->data['total_billed'],
                'total_paid'      => $this->data['total_paid'],
                'total_credited'  => $this->data['total_credited'],
                'closing_balance' => $this->data['closing_balance'],
                'company'         => $this->data['company'],
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdf,
                "account_statement_{$this->account->account_number}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}