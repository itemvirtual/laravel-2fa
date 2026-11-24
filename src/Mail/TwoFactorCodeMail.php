<?php

namespace Itemvirtual\Laravel2fa\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $code;

    public int $expires;

    public function __construct(string $code, int $expires)
    {
        $this->code = $code;
        $this->expires = $expires;
    }

    public function build()
    {
        return $this->subject(trans('laravel-2fa::laravel-2fa.mail_subject'))
            ->view('laravel-2fa::mail.code');
    }
}
