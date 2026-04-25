<?php

namespace Juniyasyos\IamClient\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class UnitKerja extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'unit_name',
        'description',
        'slug',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }

            if (empty($model->slug)) {
                $model->slug = Str::slug($model->unit_name);
            }
        });

        static::deleting(function (self $model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }
        });

        static::forceDeleting(function (self $model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }
        });
    }

    public static function isCrudAllowed(): bool
    {
        $center = (bool) config('iam.unit_kerja.center_application', false);
        $appEnv = (string) config('iam.app_env', app()->environment());

        return $center || in_array(strtolower($appEnv), ['local', 'dev', 'development'], true) || app()->environment('local');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        $userModel = config('iam.user_model', \App\Models\User::class);

        return $this->belongsToMany($userModel, 'user_unit_kerja', 'unit_kerja_id', 'user_id')->withTimestamps();
    }

    public function getUniqueValidationRules(?int $ignoreId = null): array
    {
        return [
            'unit_name' => ['required', 'string', 'max:100', 'unique:unit_kerja,unit_name,' . ($ignoreId ?? 'NULL') . ',id,deleted_at,NULL'],
        ];
    }
}
