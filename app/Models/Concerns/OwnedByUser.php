<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "own vs all" scope, reusable by every module that has an owner
 * (leads now; contacts, deals, notes and tasks later).
 *
 * A model using it needs an `owner_id` column and a static `$ownershipModule`
 * naming its permission module, e.g. 'leads' => the "leads.view-all" permission:
 *
 *   - with `{module}.view-all` a user sees every record;
 *   - without it a user sees only the records they own.
 *
 * Always read these models through ->visibleTo($user), never with a bare query.
 */
trait OwnedByUser
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Restrict the query to what $user may see.
     *
     * $viewAllPermission is the permission that lifts the restriction. It defaults to
     * the model's own ("leads.view-all"); another module that shows the same rows under
     * its own permission passes its own, e.g. the Pipeline passes "deals.view-all".
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user, ?string $viewAllPermission = null): Builder
    {
        if ($user->can($viewAllPermission ?? static::viewAllPermission())) {
            return $query;
        }

        return $query->where($this->qualifyColumn('owner_id'), $user->getKey());
    }

    public static function viewAllPermission(): string
    {
        return static::$ownershipModule.'.view-all';
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->getKey();
    }

    public function isVisibleTo(User $user, ?string $viewAllPermission = null): bool
    {
        return $this->isOwnedBy($user) || $user->can($viewAllPermission ?? static::viewAllPermission());
    }
}
