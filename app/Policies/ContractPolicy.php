<?php

namespace App\Policies;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    /**
     * Manager: all contracts. Employee/contractor: only their own, when the
     * permission is enabled on their account and the record is not a draft.
     */
    public function view(User $user, Contract $contract): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $contract->user_id === $user->id
            && $user->can_view_contract
            && $contract->status !== ContractStatus::Draft;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->isManager();
    }
}
