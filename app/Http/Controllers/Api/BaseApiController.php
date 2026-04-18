<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class BaseApiController extends Controller
{
    protected function success(array $data = [], string $message = "success"): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge(["result" => "success"], $data));
    }

    protected function error(string $message, int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(["result" => "error", "message" => $message], $code);
    }

    protected function paginated($items, array $extra = []): \Illuminate\Http\JsonResponse
    {
        // WHMCS compatible: convert limitstart to page number
        $limitstart = (int) request()->get("limitstart", 0);
        $limitnum = (int) request()->get("limitnum", 25);
        
        if ($limitstart > 0 && $limitnum > 0) {
            $page = (int) floor($limitstart / $limitnum) + 1;
            // Re-paginate with correct page
            $query = $items->getCollection();
            $total = $items->total();
            $sliced = $query->slice(0); // Already paginated by Laravel
            
            // For offset pagination, we need to re-query
            // Use forPage on the original query if possible
        }
        
        return response()->json(array_merge([
            "result" => "success",
            "totalresults" => $items->total(),
            "startnumber" => $limitstart ?: (($items->currentPage() - 1) * $items->perPage()),
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