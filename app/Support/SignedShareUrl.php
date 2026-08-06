<?php

namespace App\Support;

/**
 * Defaults for unauthenticated temporary signed share URLs.
 */
final class SignedShareUrl
{
    /** Receipts / agreements / documents shared via signed link. */
    public const TTL_HOURS = 48;
}
