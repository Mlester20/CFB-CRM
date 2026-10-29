<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Support\Facades\Gate;

class StoreLeadRequest extends LeadFormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Lead::class);
    }

    public function rules(): array
    {
        return $this->leadRules();
    }
}
