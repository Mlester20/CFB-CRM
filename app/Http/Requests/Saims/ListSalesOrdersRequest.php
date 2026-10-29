<?php

namespace App\Http\Requests\Saims;

use App\Services\Saims\SaimsService;
use Illuminate\Validation\Rule;

class ListSalesOrdersRequest extends SaimsListRequest
{
    protected function permission(): string
    {
        return 'saims.orders';
    }

    protected function filterRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(SaimsService::ORDER_STATUSES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
