<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Authorization\RoleResource;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('roles.view'), 403);

        $roles = Role::where('guard_name', 'admin')
            ->with('permissions')
            ->addSelect([
                'members_count' => DB::table('model_has_roles')
                    ->selectRaw('count(*)')
                    ->whereColumn('model_has_roles.role_id', 'roles.id')
                    ->where('model_has_roles.model_type', Admin::class),
            ])
            ->orderBy('name')
            ->get();

        $allPermissionKeys = Permission::where('guard_name', 'admin')->pluck('name');

        return Inertia::render('admin/authorization/Roles', [
            'roles' => RoleResource::collection($roles),
            'allPermissionKeys' => $allPermissionKeys,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user('admin')->can('roles.create'), 403);

        $duplicateRole = null;
        if ($request->filled('duplicate')) {
            $sourceRole = Role::where('guard_name', 'admin')->find($request->query('duplicate'));
            if ($sourceRole) {
                if ($sourceRole->is_system && ! $request->user('admin')->hasRole('Super Admin')) {
                    abort(403, 'Only Super Admins can duplicate system roles.');
                }

                $sourceRole->load('permissions');
                $duplicateRole = [
                    'source_id' => $sourceRole->id,
                    'source_name' => $sourceRole->name,
                    'name' => $this->generateUniqueDuplicateName($sourceRole->name),
                    'description' => $sourceRole->description,
                    'permissions' => $sourceRole->permissions->pluck('name')->all(),
                ];
            }
        }

        $permissions = Permission::where('guard_name', 'admin')->get()->groupBy('group_name');

        return Inertia::render('admin/authorization/RoleCreate', [
            'permissions' => $permissions,
            'initialRole' => $duplicateRole,
        ]);
    }

    protected function generateUniqueDuplicateName(string $name): string
    {
        $baseName = preg_replace('/ \(Copy(?: \d+)?\)$/', '', $name);
        $copyName = "{$baseName} (Copy)";
        $counter = 2;

        while (Role::where('guard_name', 'admin')->where('name', $copyName)->exists()) {
            $copyName = "{$baseName} (Copy {$counter})";
            $counter++;
        }

        return $copyName;
    }

    public function store(Request $request, AuditLogService $auditLogService)
    {
        abort_unless($request->user('admin')->can('roles.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'guard_name' => 'admin',
        ]);

        $role->syncPermissions($validated['permissions']);

        $auditLogService->record(
            'role.created',
            metadata: ['subject_type' => $role->getMorphClass(), 'subject_id' => $role->id],
            afterValues: $role->only(['name', 'description', 'is_system', 'guard_name']) + ['permissions' => $validated['permissions']],
            description: "Role {$role->name} was created.",
            actor: $request->user('admin'),
        );

        return redirect()->route('admin.authorization.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role)
    {
        abort_unless($request->user('admin')->can('roles.edit'), 403);

        if ($role->is_system && ! $request->user('admin')->hasRole('Super Admin')) {
            abort(403, 'Only Super Admins can manage system roles.');
        }

        $role->load('permissions');
        $permissions = Permission::where('guard_name', 'admin')->get()->groupBy('group_name');

        return Inertia::render('admin/authorization/RoleEdit', [
            'role' => new RoleResource($role),
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role, AuditLogService $auditLogService)
    {
        abort_unless($request->user('admin')->can('roles.edit'), 403);

        if ($role->is_system && ! $request->user('admin')->hasRole('Super Admin')) {
            abort(403, 'Only Super Admins can manage system roles.');
        }

        $before = $role->only(['name', 'description', 'is_system', 'guard_name']) + ['permissions' => $role->permissions->pluck('name')->all()];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->is_system && $validated['name'] !== $role->name) {
            return back()->with('error', 'System role names cannot be changed.');
        }

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($role->name !== 'Super Admin') {
            $role->syncPermissions($validated['permissions']);
        }

        $auditLogService->record(
            'role.updated',
            $role,
            beforeValues: $before,
            afterValues: $role->fresh()->only(['name', 'description', 'is_system', 'guard_name']) + ['permissions' => $validated['permissions']],
            description: "Role {$role->name} was updated.",
            actor: $request->user('admin'),
        );

        return redirect()->route('admin.authorization.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role, AuditLogService $auditLogService)
    {
        abort_unless($request->user('admin')->can('roles.delete'), 403);

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $assignedMembersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', Admin::class)
            ->count();

        if ($assignedMembersCount > 0) {
            return back()->with('error', 'Cannot delete role with assigned members.');
        }

        $before = $role->only(['name', 'description', 'is_system', 'guard_name']) + ['permissions' => $role->permissions->pluck('name')->all()];
        $roleName = $role->name;
        $role->delete();

        $auditLogService->record(
            'role.deleted',
            null,
            beforeValues: $before,
            description: "Role {$roleName} was deleted.",
            metadata: ['subject_type' => $role->getMorphClass(), 'subject_id' => $role->id],
            actor: $request->user('admin'),
        );

        return back()->with('success', 'Role deleted successfully.');
    }
}
