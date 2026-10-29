<?php

namespace App\Http\Requests\Saims;

class ListProductsRequest extends SaimsListRequest
{
    protected function permission(): string
    {
        return 'saims.products';
    }

    protected function filterRules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }
}
