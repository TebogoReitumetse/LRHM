<?php

namespace App\Policies;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\User;

class LeaveApplicationPolicy
{
    /** Owner always; Manager/Director once submitted. Admins cannot read leave records. */
    public function view(User $user, LeaveApplication $app): bool
    {
        if ($app->user_id === $user->id) {
            return true;
        }

        return $user->isManagement() && $app->status !== LeaveStatus::Draft;
    }

    public function update(User $user, LeaveApplication $app): bool
    {
        return $app->user_id === $user->id && $app->status->isEditable();
    }

    public function delete(User $user, LeaveApplication $app): bool
    {
        return $app->user_id === $user->id && $app->status === LeaveStatus::Draft;
    }

    /** Manager review actions. Nobody may act on their own application. */
    public function review(User $user, LeaveApplication $app): bool
    {
        return $user->isManager() && $app->user_id !== $user->id;
    }

    /** Final decision: Director only, never on their own application. */
    public function decide(User $user, LeaveApplication $app): bool
    {
        return $user->isDirector() && $app->user_id !== $user->id;
    }
}
