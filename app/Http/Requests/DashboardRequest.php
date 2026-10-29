<?php

namespace App\Http\Requests;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The date range of the dashboard: both ends or none (none = this year).
 */
class DashboardRequest extends FormRequest
{
    /** A longer range would mean paging through too much of SAIMS. */
    public const MAX_DAYS = 1830;

    public function authorize(): bool
    {
        return true; // the route middleware needs dashboard.view
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from')) {
                    return;
                }

                $days = CarbonImmutable::createFromFormat('Y-m-d', $this->input('from'))
                    ->diffInDays(CarbonImmutable::createFromFormat('Y-m-d', $this->input('to')));

                if ($days > self::MAX_DAYS) {
                    $validator->errors()->add('to', 'The date range can be at most 5 years.');
                }
            },
        ];
    }

    public function range(): DateRange
    {
        return DateRange::fromStrings($this->validated('from'), $this->validated('to'));
    }
}
