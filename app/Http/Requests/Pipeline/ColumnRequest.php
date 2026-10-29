<?php

namespace App\Http\Requests\Pipeline;

use App\Enums\LeadPriority;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** "Show more" of one column: the next cards, server side. */
class ColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewPipeline', Lead::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(LeadStage::class)],
            'offset' => ['required', 'integer', 'min:0', 'max:100000'],
            'search' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
        ];
    }
}
