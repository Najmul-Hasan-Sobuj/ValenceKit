<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function created(
        string $action,
        Model $subject,
        ?string $description = null,
        array $metadata = [],
        ?Model $actor = null,
    ): AuditLog {
        return $this->record(
            $action,
            $subject,
            afterValues: $this->values($subject),
            description: $description,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $metadata
     */
    public function updated(
        string $action,
        Model $subject,
        array $beforeValues,
        ?string $description = null,
        array $metadata = [],
        ?Model $actor = null,
    ): AuditLog {
        return $this->record(
            $action,
            $subject,
            beforeValues: $beforeValues,
            afterValues: $this->values($subject),
            description: $description,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $metadata
     */
    public function deleted(
        string $action,
        Model $subject,
        array $beforeValues,
        ?string $description = null,
        array $metadata = [],
        ?Model $actor = null,
    ): AuditLog {
        return $this->record(
            $action,
            $subject,
            beforeValues: $beforeValues,
            description: $description,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * Record a security or business action without storing secrets.
     *
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $afterValues
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $beforeValues = [],
        array $afterValues = [],
        ?string $description = null,
        array $metadata = [],
        ?Model $actor = null,
    ): AuditLog {
        $request = request();
        $resolvedActor = $actor ?? $this->actor();

        return AuditLog::create([
            'actor_type' => $resolvedActor?->getMorphClass(),
            'actor_id' => $resolvedActor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before_values' => $this->sanitize($beforeValues),
            'after_values' => $this->sanitize($afterValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => Context::get('request_id') ?? $request->header('X-Request-ID'),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    private function actor(): ?Model
    {
        foreach (['admin', 'mcp', 'sanctum', 'web'] as $guard) {
            $actor = Auth::guard($guard)->user();

            if ($actor instanceof Model) {
                return $actor;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Model $subject): array
    {
        if (method_exists($subject, 'auditLogValues')) {
            return $subject->auditLogValues();
        }

        return $subject->getAttributes();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'secret', 'client_secret', 'plain_text_token'];

        return collect($values)->mapWithKeys(function (mixed $value, string|int $key) use ($sensitive): array {
            $keyString = strtolower((string) $key);

            if (collect($sensitive)->contains(fn (string $term): bool => str_contains($keyString, $term))) {
                return [$key => '[REDACTED]'];
            }

            if (is_array($value)) {
                return [$key => $this->sanitize($value)];
            }

            return [$key => $value];
        })->all();
    }
}
