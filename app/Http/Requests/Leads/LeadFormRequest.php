<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and editing a lead.
 */
abstract class LeadFormRequest extends FormRequest
{
    /**
     * The user may choose the owner only with leads.view-all.
     * For everyone else owner_id is never read, whatever the client sends.
     */
    public function mayAssignOwner(): bool
    {
        return (bool) $this->user()?->can(Lead::viewAllPermission());
    }

    /**
     * @param  int|null  $currentOwnerId  Kept valid on edit even if that user was deactivated since.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function leadRules(?int $currentOwnerId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-\s.]+$/'],
            'stage' => ['required', Rule::enum(LeadStage::class)],
            'priority' => ['required', Rule::enum(LeadPriority::class)],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->mayAssignOwner()) {
            $rules['owner_id'] = [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentOwnerId),
                ),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number may only contain digits, spaces and + - ( ) .',
        ];
    }
}
