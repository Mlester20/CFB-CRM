<?php

namespace App\Http\Resources;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The only shape in which a lead leaves the server. Plain static helpers
 * (not a JsonResource) so it works inside paginator->through().
 */
class LeadResource
{
    /**
     * A row for the list and the cards. `can` says what the user may do with
     * this lead, so the menus show only what is allowed.
     *
     * @return array<string, mixed>
     */
    public static function row(Lead $lead, User $user): array
    {
        $gate = Gate::forUser($user);

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'email' => $lead->email,
            'stage' => $lead->stage->value,
            'priority' => $lead->priority->value,
            'source' => $lead->source->value,
            'value' => (float) $lead->value,
            'owner' => $lead->owner ? ['id' => $lead->owner->id, 'name' => $lead->owner->name] : null,
            'updated_at' => $lead->updated_at?->toIso8601String(),
            'can' => [
                'update' => $gate->allows('update', $lead),
                'delete' => $gate->allows('delete', $lead),
            ],
        ];
    }

    /**
     * Every field, for the drawer and the edit form.
     *
     * @return array<string, mixed>
     */
    public static function detail(Lead $lead, User $user): array
    {
        return [
            ...self::row($lead, $user),
            'phone' => $lead->phone,
            'notes' => $lead->notes,
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
