<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        return $this->sameOrganization($user, $contract)
            && $user->can('contracts.view');
    }

    public function manage(User $user, Contract $contract): bool
    {
        return $this->sameOrganization($user, $contract)
            && $user->can('contracts.manage');
    }

    public function settle(User $user, Contract $contract): bool
    {
        return $this->sameOrganization($user, $contract)
            && $user->can('contracts.settle');
    }

    private function sameOrganization(User $user, Contract $contract): bool
    {
        return (int) $user->organization_id === (int) $contract->organization_id;
    }
}
