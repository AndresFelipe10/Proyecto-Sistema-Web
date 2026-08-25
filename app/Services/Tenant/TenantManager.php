<?php

namespace App\Services\Tenant;

use App\Models\Business;

class TenantManager
{
    /**
     * The currently active business (tenant).
     */
    protected ?Business $tenant = null;

    /**
     * Set the currently active tenant.
     */
    public function set(?Business $business): void
    {
        $this->tenant = $business;
    }

    /**
     * Get the currently active tenant.
     */
    public function get(): ?Business
    {
        return $this->tenant;
    }

    /**
     * Get the ID of the currently active tenant.
     */
    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Check if a tenant is currently set.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Check if the given business ID matches the current tenant.
     */
    public function check(int $businessId): bool
    {
        return $this->id() === $businessId;
    }

    /**
     * Clear the currently active tenant.
     */
    public function clear(): void
    {
        $this->tenant = null;
    }
}
