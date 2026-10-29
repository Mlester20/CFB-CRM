<?php

namespace App\Http\Requests\Saims;

use App\Services\Saims\SaimsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query string of a SAIMS list page. Only the parameters a page
 * declares are ever read, so nothing else can reach SAIMS.
 */
abstract class SaimsListRequest extends FormRequest
{
    /** The saims.* permission the page needs. */
    abstract protected function permission(): string;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function filterRules(): array;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->permission());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // Not rejected when too big: it is clamped to 100 in filters().
            'per_page' => ['nullable', 'integer', 'min:1'],
            ...$this->filterRules(),
        ];
    }

    /**
     * The validated filters, with page and per_page filled in and clamped.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->validated(),
            fn ($value) => $value !== null && $value !== '',
        );

        $filters['page'] = (int) ($filters['page'] ?? 1);
        $filters['per_page'] = min(SaimsService::MAX_PER_PAGE, (int) ($filters['per_page'] ?? SaimsService::DEFAULT_PER_PAGE));

        return $filters;
    }
}
