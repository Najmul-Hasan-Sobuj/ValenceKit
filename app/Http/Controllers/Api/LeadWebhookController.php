<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportLeadRequest;
use App\Models\Lead;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeadWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ImportLeadRequest $request, AuditLogService $audit): JsonResponse
    {
        return DB::transaction(function () use ($request, $audit): JsonResponse {
            $data = $request->validated();
            $lead = Lead::firstOrCreate(
                ['source' => $data['source'], 'source_id' => $data['source_id']],
                $data,
            );
            if ($lead->wasRecentlyCreated) {
                $audit->created('lead.imported', $lead, actor: $request->user('leads'));
            } else {
                $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
                $before = $lead->auditLogValues();
                foreach ($data as $key => $value) {
                    if ($key === 'details') {
                        $lead->details = array_replace_recursive($lead->details ?? [], $value ?? []);
                    } elseif (! in_array($key, ['source', 'source_id', 'name']) && blank($lead->getAttribute($key)) && filled($value)) {
                        $lead->setAttribute($key, $value);
                    }
                }
                if ($lead->isDirty()) {
                    $lead->save();
                    $audit->updated('lead.enriched', $lead, $before, actor: $request->user('leads'));
                }
            }

            return response()->json(['data' => ['id' => $lead->id], 'created' => $lead->wasRecentlyCreated], $lead->wasRecentlyCreated ? 201 : 200);
        });
    }
}
