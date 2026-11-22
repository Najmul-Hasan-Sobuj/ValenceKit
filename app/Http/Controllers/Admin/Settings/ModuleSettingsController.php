<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateModuleSettingsRequest;
use App\Models\Module;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.modules.view'), 403);

        return Inertia::render('admin/settings/Modules', [
            'modules' => Module::configured(),
        ]);
    }

    public function update(UpdateModuleSettingsRequest $request, AuditLogService $auditLogService): RedirectResponse
    {
        $before = Module::states();
        $modules = $request->validated('modules');

        foreach ($modules as $key => $enabled) {
            Module::query()->updateOrCreate(['key' => $key], ['enabled' => $enabled]);
        }

        $auditLogService->record(
            'settings.modules.updated',
            afterValues: $modules,
            beforeValues: $before,
            description: 'Application modules were enabled or disabled.',
            actor: $request->user('admin'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Module settings updated successfully.'),
        ]);

        return back()->with('success', 'Module settings updated successfully.');
    }
}
