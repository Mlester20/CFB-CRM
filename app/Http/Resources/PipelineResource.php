<?php

namespace App\Http\Resources;

use App\Models\Lead;

/**
 * A card of the Pipeline board: just what the card shows. The deal value is the lead's
 * own value, visible to anyone who can see the lead (the same rule as the Leads list);
 * the money AGGREGATES of the board are separate and need dashboard.view-revenue.
 */
class PipelineResource
{
    /** @return array<string, mixed> */
    public static function card(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'value' => (float) $lead->value,
            'priority' => $lead->priority->value,
            'stage' => $lead->stage->value,
            'position' => $lead->pipeline_position,
        ];
    }
}
