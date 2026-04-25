<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Juniyasyos\IamClient\Models\UnitKerja;

class CenterSyncController extends Controller
{
    public function __invoke(): \Illuminate\Http\JsonResponse
    {
        if (! Config::get('iam.unit_kerja.center_application', false)) {
            return response()->json(['message' => 'App center tidak diaktifkan pada konfigurasi ini.'], 403);
        }

        $unitModel = Config::get('iam.unit_kerja_model', UnitKerja::class);
        $userModel = Config::get('iam.user_model', \App\Models\User::class);

        $unitInstance = new $unitModel();
        $userInstance = new $userModel();

        $units = $unitModel::query()
            ->whereNull('deleted_at')
            ->get(['id', 'unit_name', 'description', 'slug', 'created_at', 'updated_at']);

        $userQuery = $userModel::query()
            ->when(
                method_exists($userModel, 'trashed') || method_exists($userInstance, 'getDeletedAtColumn'),
                fn($query) => $query->whereNull('deleted_at')
            );

        $userTable = $userInstance->getTable();
        $existingColumns = Schema::getColumnListing($userTable);

        $selectColumns = [];
        foreach (['id', 'nip', 'name', 'email', 'status', 'created_at', 'updated_at'] as $col) {
            if (in_array($col, $existingColumns, true)) {
                $selectColumns[] = $col;
            }
        }

        if (! in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }

        $users = $userQuery->get($selectColumns)->map(function ($user) {
            $user->iam_id = $user->id;
            return $user;
        });

        $unitTable = $unitInstance->getTable();

        $relations = \Illuminate\Support\Facades\DB::table('user_unit_kerja')
            ->join($userTable, 'user_unit_kerja.user_id', '=', "{$userTable}.id")
            ->join($unitTable, 'user_unit_kerja.unit_kerja_id', '=', "{$unitTable}.id")
            ->select(
                'user_unit_kerja.user_id',
                'user_unit_kerja.unit_kerja_id',
                'user_unit_kerja.created_at as attached_at',
                'user_unit_kerja.updated_at as attached_updated_at',
                "{$userTable}.nip as user_nip",
                "{$userTable}.email as user_email",
                "{$unitTable}.slug as unit_slug"
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'units' => $units,
                'users' => $users,
                'user_unit_kerja' => $relations,
            ],
        ]);
    }
}
