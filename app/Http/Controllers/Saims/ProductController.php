<?php

namespace App\Http\Controllers\Saims;

use App\Http\Requests\Saims\ListProductsRequest;
use App\Services\Saims\SaimsService;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends SaimsController
{
    protected function permission(): string
    {
        return 'saims.products';
    }

    public function index(ListProductsRequest $request, SaimsService $saims): Response
    {
        return $this->render($request, $saims);
    }

    public function show(ListProductsRequest $request, SaimsService $saims, int $id): Response
    {
        $this->guard($request);

        [$record, $error] = $this->attempt(fn () => $saims->productById($id), notFoundIs404: true);

        return $this->render($request, $saims, ['id' => $id, 'record' => $record, 'error' => $error]);
    }

    /**
     * @param  array{id: int, record: ?array<string, mixed>, error: ?array<string, string>}|null  $drawer
     */
    private function render(ListProductsRequest $request, SaimsService $saims, ?array $drawer = null): Response
    {
        $this->guard($request);

        $filters = $request->filters();
        [$list, $error] = $this->attempt(fn () => $saims->products($filters));

        return Inertia::render('Saims/Products/Index', [
            'records' => $list,
            'filters' => $filters,
            'error' => $error,
            'drawer' => $drawer,
        ]);
    }
}
