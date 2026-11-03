<?php

namespace App\Models\Concerns;

use App\Models\Aip;
use App\Models\AipKra;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait BelongsToOrganization
{
    /** Asking the database for a table's columns costs a query each time, so a "yes" is remembered for the rest of the request (a "no" is asked again, in case a migration adds the column). */
    protected static function tracksOrganization(string $table): bool
    {
        static $known = [];

        if (isset($known[$table])) {
            return true;
        }

        if (! Schema::hasColumn($table, 'organization_id')) {
            return false;
        }

        return $known[$table] = true;
    }

    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function ($query) {
            $user = Auth::hasUser() ? Auth::user() : null;
            $table = $query->getModel()->getTable();
            if ($user && ! $user->seesAllSchools() && static::tracksOrganization($table)) {
                // A tenant user without an organization must never fall back to an
                // unscoped query. Returning no rows is safer than leaking data.
                if (! $user->organization_id) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where($table.'.organization_id', $user->organization_id);
            }
        });

        static::saving(function ($model) {
            if (! static::tracksOrganization($model->getTable())) {
                return;
            }

            if ($model->organization_id) {
                return;
            }

            if ($model->school_id) {
                $model->organization_id = School::withoutGlobalScopes()->whereKey($model->school_id)->value('organization_id');
            } elseif ($model->procurement_request_id) {
                $model->organization_id = ProcurementRequest::withoutGlobalScopes()->whereKey($model->procurement_request_id)->value('organization_id');
            } elseif ($model->aip_id) {
                $model->organization_id = Aip::withoutGlobalScopes()->whereKey($model->aip_id)->value('organization_id');
            } elseif ($model->aip_kra_id) {
                $model->organization_id = AipKra::withoutGlobalScopes()->whereKey($model->aip_kra_id)->value('organization_id');
            }

            if (! $model->organization_id && Auth::check() && Auth::user()->organization_id) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });

        static::saving(function ($model) {
            if (! static::tracksOrganization($model->getTable())) {
                return;
            }

            $user = Auth::user();
            if (! $user || $user->seesAllSchools()) {
                return;
            }

            // A Sub-master with no area on still keeps their own account and the audit trail of their own actions.
            if ($user->isAnyMaster() && (
                ($model instanceof User && $model->is($user))
                || ($model instanceof AuditLog && (int) $model->user_id === (int) $user->id)
            )) {
                return;
            }

            if (! $user->organization_id) {
                abort(403, 'Your account is not assigned to an organization.');
            }

            if ((int) $model->organization_id !== (int) $user->organization_id) {
                abort(403, 'You cannot modify records outside your organization.');
            }

            if ($model->exists && $model->isDirty('organization_id')) {
                abort(403, 'Organization assignment cannot be changed.');
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
