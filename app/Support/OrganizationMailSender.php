<?php

namespace App\Support;

use Illuminate\Mail\Mailables\Address;

final class OrganizationMailSender
{
    public static function fromAddress(?string $organizationName): Address
    {
        $name = is_string($organizationName) ? trim($organizationName) : '';

        return new Address(
            (string) config('mail.from.address'),
            $name !== '' ? $name : (string) config('mail.from.name'),
        );
    }
}
