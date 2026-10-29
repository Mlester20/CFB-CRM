<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BulkDeleteLeadsRequest extends FormRequest
{
    public const MAX_IDS = 100;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('deleteAny', Lead::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
