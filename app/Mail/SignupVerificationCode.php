<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SignupVerificationCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function build(): static
    {
        return $this->subject('Verify your IDireksyon email')
            ->text('mail.signup-verification-code');
    }
}
