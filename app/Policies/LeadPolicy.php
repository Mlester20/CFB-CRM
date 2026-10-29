<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeadPolicy extends OwnedModelPolicy
{
    /** The permission that lifts the own/all restriction on the Pipeline board. */
    public const PIPELINE_VIEW_ALL = 'deals.view-all';

    protected function module(): string
    {
        return 'leads';
    }

    /** Who may open the Pipeline page. */
    public function viewPipeline(User $user): bool
    {
        return $user->can('deals.view');
    }

    /**
     * Open a lead from the board. Same rule as the Leads module, but with the deals
     * permissions: a lead outside the user's deals scope is a 404.
     */
    public function viewInPipeline(User $user, Lead $lead): Response
    {
        if (! $lead->isVisibleTo($user, self::PIPELINE_VIEW_ALL)) {
            return Response::denyAsNotFound();
        }

        return $user->can('deals.view') ? Response::allow() : Response::deny();
    }

    /**
     * Move a card. Outside the user's deals scope: 404 (its existence is not revealed).
     * Inside it but without deals.move: 403.
     */
    public function move(User $user, Lead $lead): Response
    {
        if (! $lead->isVisibleTo($user, self::PIPELINE_VIEW_ALL)) {
            return Response::denyAsNotFound();
        }

        return $user->can('deals.move') ? Response::allow() : Response::deny();
    }
}
