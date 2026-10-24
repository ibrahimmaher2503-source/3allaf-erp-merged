<?php

declare(strict_types=1);

namespace App\Mail;

use App\Modules\Retail\Models\Sale;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PosInvoiceReceipt extends Mailable
{
    public function __construct(public readonly Sale $sale) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Sales invoice').' '.$this->sale->document_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pos-invoice-receipt');
    }
}
