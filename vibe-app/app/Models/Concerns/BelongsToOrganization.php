<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function ($query) {
            $user = Auth::hasUser() ? Auth::user() : null;
            if ($user && $user->role !== 'master_user' && $user->organization_id) {
                $query->where($query->getModel()->getTable() . '.organization_id', $user->organization_id);
            }
        });

        static::creating(function ($model) {
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
