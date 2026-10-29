<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Support\Facades\Gate;

class UpdateLeadRequest extends LeadFormRequest
{
    /**
     * Resolves the lead here (before validation) so a lead outside the user's
     * scope answers 404 even for invalid input, never a revealing 422.
     */
    public function authorize(): bool
    {
        $lead = Lead::findOrFail($this->route('lead'));

        // Throws a 404 (not visible) or 403 (visible but not allowed).
        Gate::forUser($this->user())->authorize('update', $lead);

        return true;
    }

    public function rules(): array
    {
        return $this->leadRules(Lead::find($this->route('lead'))?->owner_id);
    }
}
