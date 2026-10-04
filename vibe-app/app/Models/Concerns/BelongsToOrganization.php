<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function ($query) {
            $user = Auth::hasUser() ? Auth::user() : null;
            $table = $query->getModel()->getTable();
            if ($user && $user->role !== 'master_user' && $user->organization_id && Schema::hasColumn($table, 'organization_id')) {
                $query->where($table . '.organization_id', $user->organization_id);
            }
        });

        static::creating(function ($model) {
            if (!Schema::hasColumn($model->getTable(), 'organization_id')) {
                return;
            }

            if ($model->organization_id) {
                return;
            }

            if ($model->school_id) {
                $model->organization_id = \App\Models\School::withoutGlobalScopes()->whereKey($model->school_id)->value('organization_id');
            } elseif ($model->procurement_request_id) {
                $model->organization_id = \App\Models\ProcurementRequest::withoutGlobalScopes()->whereKey($model->procurement_request_id)->value('organization_id');
            }

            if (!$model->organization_id && Auth::check() && Auth::user()->organization_id) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
