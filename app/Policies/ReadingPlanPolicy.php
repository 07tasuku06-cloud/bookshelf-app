<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 読書計画一覧の閲覧を許可する。
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * 所有者に読書計画の閲覧を許可する。
     */
    public function view(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * ログイン済みユーザーに読書計画の作成を許可する。
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 所有者かつ未読了の場合に更新を許可する。
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 所有者に読書計画の削除を許可する。
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * 所有者かつ未読了の場合に読了操作を許可する。
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }
}
