<?php

namespace App\Http\Requests\Pipeline;

use App\Enums\LeadPriority;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Moves one card. The lead is resolved and authorized BEFORE validation, so a card
 * the user may not even see answers 404 (never a revealing 422), and one they may
 * see but not move answers 403.
 */
class MoveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = Lead::findOrFail($this->route('lead'));

        // Throws a 404 (not in the user's deals scope) or a 403 (no deals.move).
        Gate::forUser($this->user())->authorize('move', $lead);

        return true;
    }

    /**
     * The stage is validated against the enum; position and the optional search and
     * priority (which only decide which column totals come back) are plain, bounded values.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(LeadStage::class)],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'search' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
        ];
    }
}
