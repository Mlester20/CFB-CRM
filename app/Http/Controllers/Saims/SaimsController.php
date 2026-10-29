<?php

namespace App\Http\Controllers\Saims;

use App\Http\Controllers\Controller;
use App\Services\Saims\SaimsException;
use App\Services\Saims\SaimsFailure;
use Illuminate\Http\Request;

/**
 * Shared by the four read-only SAIMS pages. A page only ever calls the fixed
 * SaimsService methods of its own module: the browser chooses no URL or path.
 */
abstract class SaimsController extends Controller
{
    /** The saims.* permission this page needs. */
    abstract protected function permission(): string;

    /**
     * Second line of defence behind the route middleware and the form request:
     * nothing in a controller runs, and so nothing is called on SAIMS, without it.
     */
    protected function guard(Request $request): void
    {
        abort_unless($request->user()?->can($this->permission()), 403);
    }

    /**
     * Runs a SAIMS call. A failure becomes a friendly { code, message } for the
     * page (the rest of the CRM keeps working); a missing record is a 404.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return array{0: T|null, 1: array{code: string, message: string}|null}
     */
    protected function attempt(callable $call, bool $notFoundIs404 = false): array
    {
        try {
            return [$call(), null];
        } catch (SaimsException $e) {
            if ($notFoundIs404 && $e->failure === SaimsFailure::NotFound) {
                abort(404);
            }

            return [null, $e->toArray()];
        }
    }
}
