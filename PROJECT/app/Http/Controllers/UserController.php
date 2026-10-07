<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * MASTER_PROJECT_AUDIT.md backlog item (found while building the
 * Complaints frontend, 2026-09-20): there was no way to list a tenant's
 * own staff anywhere in the API, so no UI (Complaints assignment, Lead
 * assignment, etc.) could ever build a "who can this go to" dropdown.
 * Deliberately minimal: read-only, active-staff-only, no create/update/
 * delete here — user provisioning already exists via
 * TenantController/registration and is out of scope for this fix.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::where('tenant_id', $request->user->tenant_id)
            ->where('is_active', true)
            ->with('roles:id,name,display_name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return response()->json(['data' => $users]);
    }
}
