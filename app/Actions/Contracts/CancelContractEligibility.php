<?php

namespace App\Actions\Contracts;

final readonly class CancelContractEligibility
{
    /**
     * @param  list<array{code: string, message: string, action_url: ?string, action_label: ?string}>  $blockers
     */
    public function __construct(
        public bool $allowed,
        public array $blockers = [],
    ) {}
}
