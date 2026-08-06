<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $this->sameOrganization($user, $payment)
            && $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    private function sameOrganization(User $user, Payment $payment): bool
    {
        return (int) $user->organization_id === (int) $payment->organization_id;
    }
}
