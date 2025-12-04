<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $pdf;

    public function __construct($order, $pdf)
    {
        $this->order = $order;
        $this->pdf = $pdf;
    }

    public function build()
    {
        return $this->subject('Struk Pembayaran - Order #' . $this->order->id)
            ->view('emails.receipt')
            ->attachData($this->pdf->output(), 'receipt.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}