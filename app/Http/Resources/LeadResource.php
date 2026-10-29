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
            ...self::saims($lead, $user),
            'can' => [
                'update' => $gate->allows('update', $lead),
                'delete' => $gate->allows('delete', $lead),
            ],
        ];
    }

    /**
     * The SAIMS link. Only users with a saims.* permission learn that a lead is
     * linked, and only a user with saims.customers sees which customer.
     * Reading it is a local column: it never calls SAIMS.
     *
     * @return array<string, mixed>
     */
    private static function saims(Lead $lead, User $user): array
    {
        $hasAny = $user->canAny(['saims.customers', 'saims.orders', 'saims.invoices']);

        if (! $hasAny) {
            return [];
        }

        $data = ['saims_linked' => $lead->saims_customer_id !== null];

        if ($user->can('saims.customers')) {
            $data['saims_customer'] = $lead->saims_customer_id !== null
                ? ['id' => $lead->saims_customer_id, 'name' => $lead->saims_customer_name]
                : null;
        }

        return $data;
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
