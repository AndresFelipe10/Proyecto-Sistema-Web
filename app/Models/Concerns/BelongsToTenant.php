<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Scopes\TenantScope;
use App\Services\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            $tenantManager = app(TenantManager::class);

            if ($tenantManager->hasTenant() && empty($model->business_id)) {
                $model->business_id = $tenantManager->id();
            }
        });
    }

    /**
     * Get the business that owns this entity.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
