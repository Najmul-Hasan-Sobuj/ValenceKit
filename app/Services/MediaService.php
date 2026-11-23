<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Media;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaService
{
    public function __construct(private HrService $hr, private AuditLogService $audit) {}

    /** @return Builder<Media> */
    public function visibleTo(Admin $actor): Builder
    {
        return Media::query()->where(function (Builder $query) use ($actor): void {
            $query->whereNull('mediable_type');
            if (Module::enabled('people') && ($actor->can('employees.view') || $actor->can('hr.self') || $actor->can('hr.team'))) {
                $query->orWhere(fn (Builder $query) => $query->where('mediable_type', (new Employee)->getMorphClass())->whereIn('mediable_id', $this->hr->employees($actor)->select('employees.id')));
            }
        });
    }

    public function authorizeOwner(Admin $actor, ?Model $owner, bool $write = false): void
    {
        if ($owner === null) {
            return;
        }
        abort_unless($owner instanceof Employee && Module::enabled('people'), 404);
        abort_unless($actor->can('employees.view') || $actor->can('hr.self') || $actor->can('hr.team'), 404);
        abort_unless($this->hr->employees($actor)->whereKey($owner->getKey())->exists(), 404);
        if ($write) {
            abort_unless($actor->can('employees.edit'), 403);
        }
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file): array
    {
        $path = $file->store('media/'.now()->format('Y/m'), 'local');
        if ($path === false) {
            throw new RuntimeException('The image could not be stored.');
        }
        [$width, $height] = getimagesize($file->getPathname());

        return ['disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'width' => $width, 'height' => $height];
    }

    /** @param list<UploadedFile> $files */
    public function upload(array $files, Admin $actor, ?Model $owner, string $collection, string $folder): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($files, $actor, $owner, $collection, $folder, &$stored): void {
                if ($owner) {
                    $owner->newQuery()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
                    if ($collection === 'avatar') {
                        if ($owner->media()->where('collection', 'avatar')->exists()) {
                            abort_unless($actor->can('media.edit'), 403);
                        }
                        $owner->media()->where('collection', 'avatar')->get()->each(fn (Media $media) => $this->delete($media));
                    }
                }
                foreach ($files as $file) {
                    $attributes = $this->storeFile($file);
                    $stored[] = $attributes;
                    $media = new Media([
                        ...$attributes, 'uploaded_by' => $actor->id, 'collection' => $collection,
                        'name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Untitled image', 255, ''), 'folder' => $folder,
                    ]);
                    $media->mediable()->associate($owner);
                    $media->save();
                    $this->audit->created('media.created', $media);
                }
            });
        } catch (Throwable $exception) {
            foreach ($stored as $attributes) {
                Storage::disk($attributes['disk'])->delete($attributes['path']);
            }
            throw $exception;
        }
    }

    /** @param array{name: string, alt?: ?string, folder: string} $data */
    public function update(Media $media, array $data, ?UploadedFile $file): void
    {
        $stored = $file ? $this->storeFile($file) : null;
        try {
            DB::transaction(function () use ($media, $data, $stored): void {
                $media = Media::lockForUpdate()->findOrFail($media->id);
                $before = $media->auditLogValues();
                $disk = $media->disk;
                $path = $media->path;
                $media->update([...$data, ...($stored ?? [])]);
                $this->audit->updated('media.updated', $media, $before);
                if ($stored) {
                    DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
                }
            });
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }
            throw $exception;
        }
    }

    public function delete(Media $media): void
    {
        DB::transaction(function () use ($media): void {
            $media = Media::lockForUpdate()->findOrFail($media->id);
            $disk = $media->disk;
            $path = $media->path;
            $this->audit->deleted('media.deleted', $media, $media->auditLogValues());
            $media->delete();
            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        });
    }
}
