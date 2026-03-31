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
        return response()->json(array_merge([
            "result" => "success",
            "totalresults" => $items->total(),
            "startnumber" => ($items->currentPage() - 1) * $items->perPage(),
            "numreturned" => $items->count(),
            "data" => $items->items(),
        ], $extra));
    }
}
