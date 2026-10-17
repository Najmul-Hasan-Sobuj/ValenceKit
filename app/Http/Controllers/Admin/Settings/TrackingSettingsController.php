<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateTrackingSettingsRequest;
use App\Models\TrackingSetting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingSettingsController extends Controller
{
    /**
     * Display the tracking and analytics settings tab.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.tracking.view'), 403);

        $settings = TrackingSetting::instance();

        return Inertia::render('admin/settings/Tracking', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update tracking settings, refresh cache, and record audit log.
     */
    public function update(UpdateTrackingSettingsRequest $request, AuditLogService $auditLogService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.tracking.edit'), 403);

        $settings = TrackingSetting::first() ?? TrackingSetting::create([]);

        $attributes = [
            'clarity_enabled',
            'clarity_project_id',
            'google_analytics_enabled',
            'google_analytics_id',
            'google_tag_manager_enabled',
            'google_tag_manager_id',
            'facebook_pixel_enabled',
            'facebook_pixel_id',
            'custom_scripts_enabled',
            'custom_head_scripts',
            'custom_body_scripts',
            'exclude_admins',
        ];

        $before = $settings->only($attributes);
        $validated = $request->validated();

        $settings->update($validated);
        TrackingSetting::flushCache();

        $auditLogService->record(
            'settings.tracking.updated',
            afterValues: $validated,
            beforeValues: $before,
            description: 'Tracking, analytics, and pixels configuration was updated.',
            actor: $request->user('admin'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tracking configuration updated successfully.'),
        ]);

        return back()->with('success', 'Tracking configuration updated successfully.');
    }
}
