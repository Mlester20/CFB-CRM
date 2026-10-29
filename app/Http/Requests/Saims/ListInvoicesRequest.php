<?php

namespace App\Http\Requests\Saims;

class ListInvoicesRequest extends SaimsListRequest
{
    protected function permission(): string
    {
        return 'saims.invoices';
    }

    protected function filterRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
