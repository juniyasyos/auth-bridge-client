<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Juniyasyos\IamClient\Services\UnitKerjaSyncService;

class ClientSyncController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Config::get('iam.unit_kerja.sync.active', false)) {
            return response()->json(['message' => 'Sinkronisasi tidak aktif.'], 403);
        }

        $centerUrl = Config::get('iam.unit_kerja.app_center_url');

        if (empty($centerUrl)) {
            return response()->json(['message' => 'URL App Center belum diset.'], 422);
        }

        $provisionEndpoint = rtrim($centerUrl, '/') . '/api/manage-unit-kerja/center/provision';

        $response = Http::timeout(15)->get($provisionEndpoint);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Gagal mendapatkan data dari App Center.',
                'status' => 'error',
                'http_status' => $response->status(),
            ], 500);
        }

        $payload = $response->json();

        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return response()->json(['message' => 'Format data dari App Center tidak valid.'], 500);
        }

        $service = new UnitKerjaSyncService();
        $result = $service->sync($payload);

        return response()->json($result);
    }
}
