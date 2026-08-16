<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    // daftar istilah populer untuk suggestions
    public function popular(): JsonResponse
    {
        return response()->json(SearchService::popular());
    }
}