<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

/**
 * PHASE 15 SECURITY AUDIT FINDING: ApiKeyController called
 * $this->authorize('view'/'update'/'delete', $apiKey) throughout, but no
 * ApiKeyPolicy existed anywhere and none was registered — every one of
 * those calls would throw AuthorizationException regardless of the
 * user's actual permissions, since Laravel's Gate::authorize() fails
 * closed (denies) when it can't resolve an ability rather than allowing
 * it through. These endpoints were completely unusable, for anyone.
 */
class ApiKeyPolicy
{
    public function view(User $user, ApiKey $apiKey): bool
    {
        return $user->tenant_id === $apiKey->tenant_id;
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $user->tenant_id === $apiKey->tenant_id;
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->tenant_id === $apiKey->tenant_id;
    }
}
