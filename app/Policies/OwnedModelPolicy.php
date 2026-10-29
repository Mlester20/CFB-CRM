<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for modules that use App\Models\Concerns\OwnedByUser.
 *
 * Two different answers, on purpose:
 *   - a record the user is not allowed to SEE (outside their own/all scope)
 *     answers 404, so its existence is not revealed;
 *   - a record the user can see but lacks the permission for answers 403.
 *
 * A module policy only names its permission module, e.g. 'leads'.
 */
abstract class OwnedModelPolicy
{
    /** The permission prefix, e.g. 'leads' for "leads.view". */
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->module().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->module().'.create');
    }

    public function export(User $user): bool
    {
        return $user->can($this->module().'.export');
    }

    /** Bulk delete: the permission only; each record is still checked with delete(). */
    public function deleteAny(User $user): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function view(User $user, Model $model): Response
    {
        return $this->inScope($user, $model, 'view');
    }

    public function update(User $user, Model $model): Response
    {
        return $this->inScope($user, $model, 'update');
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->inScope($user, $model, 'delete');
    }

    /**
     * @param  Model&\App\Models\Concerns\OwnedByUser  $model
     */
    private function inScope(User $user, Model $model, string $ability): Response
    {
        if (! $model->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $user->can($this->module().'.'.$ability) ? Response::allow() : Response::deny();
    }
}
