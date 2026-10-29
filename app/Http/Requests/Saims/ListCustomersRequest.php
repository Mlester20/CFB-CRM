<?php

namespace App\Http\Requests\Saims;

class ListCustomersRequest extends SaimsListRequest
{
    protected function permission(): string
    {
        return 'saims.customers';
    }

    protected function filterRules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }
}
