<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Juniyasyos\IamClient\Services\UnitKerjaSyncService;

class ClientPushUnitKerjaController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Config::get('iam.unit_kerja.push.active', true)) {
            return response()->json(['message' => 'Push Unit Kerja tidak aktif.'], 403);
        }

        $payload = $request->json()->all();
        $service = new UnitKerjaSyncService();
        $result = $service->sync($payload);

        return response()->json($result);
    }
}
