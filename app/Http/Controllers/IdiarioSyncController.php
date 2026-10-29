<?php

namespace App\Http\Controllers;

use App\Services\Idiario\IdiarioSync;
use Illuminate\Http\JsonResponse;

class IdiarioSyncController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json(IdiarioSync::snapshot(refresh: true));
    }

    public function dismiss(): JsonResponse
    {
        IdiarioSync::dismiss();

        return response()->json(IdiarioSync::snapshot());
    }
}
