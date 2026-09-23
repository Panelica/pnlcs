<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class BaseApiController extends Controller
{
    protected function success(array $data = [], string $message = "success"): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge(["result" => "success"], $data));
    }

    /**
     * Read a parameter under the name WHMCS gives it, too.
     *
     * The reference screen documents the WHMCS names (userid, clientid,
     * duedate, validuntil...), while several endpoints read only the name the
     * code happened to use, so a caller following the documentation was
     * silently ignored - an unfiltered list, or "not found" for a record that
     * exists. The documented name is copied across when the other is absent.
     */
    protected function alias(\Illuminate\Http\Request $request, string $documented, string $read): void
    {
        if (! $request->has($read) && $request->has($documented)) {
            $request->merge([$read => $request->input($documented)]);
        }
    }

    protected function error(string $message, int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(["result" => "error", "message" => $message], $code);
    }

    /**
     * A page of results in the WHMCS list shape.
     *
     * startnumber is where this page really begins. It used to echo the
     * limitstart the caller asked for, while the rows came from the page that
     * offset falls in - limitstart=10 with limitnum=25 said "from 10" and
     * returned rows 0-24. A caller paging by startnumber skipped or repeated
     * records.
     */
    protected function paginated($items, array $extra = []): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            "result" => "success",
            "totalresults" => $items->total(),
            "startnumber" => ($items->currentPage() - 1) * $items->perPage(),
            "numreturned" => $items->count(),
            "data" => $items->items(),
        ], $extra));
    }

    /**
     * Get the correct page number from WHMCS-style limitstart/limitnum params.
     */
    protected function getPage(): int
    {
        $limitstart = (int) request()->get("limitstart", 0);
        $limitnum = max(1, (int) request()->get("limitnum", 25));
        return $limitstart > 0 ? (int) floor($limitstart / $limitnum) + 1 : (int) request()->get("page", 1);
    }

    protected function getPerPage(): int
    {
        return max(1, min(250, (int) request()->get("limitnum", 25)));
    }
}