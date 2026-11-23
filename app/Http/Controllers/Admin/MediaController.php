<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\StoreMediaRequest;
use App\Http\Requests\Admin\Media\UpdateMediaRequest;
use App\Http\Resources\Admin\MediaResource;
use App\Models\Employee;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(private MediaService $images) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor->can('media.view'), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'folder' => ['nullable', 'string', 'max:100'], 'usage' => ['nullable', 'in:all,attached,unattached']]);
        $visible = $this->images->visibleTo($actor);
        $media = (clone $visible)->with('mediable')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('alt', 'like', '%'.$search.'%')))
            ->when($filters['folder'] ?? null, fn ($q, $folder) => $q->where('folder', $folder))
            ->when(($filters['usage'] ?? '') === 'attached', fn ($q) => $q->whereNotNull('mediable_type'))
            ->when(($filters['usage'] ?? '') === 'unattached', fn ($q) => $q->whereNull('mediable_type'))
            ->latest('id')->paginate(24)->withQueryString();

        if ($media->currentPage() > $media->lastPage()) {
            return to_route('admin.media.index', [...$request->query(), 'page' => $media->lastPage()]);
        }

        return Inertia::render('admin/media/Index', [
            'media' => MediaResource::collection($media), 'filters' => $filters,
            'folders' => (clone $visible)->select('folder')->distinct()->orderBy('folder')->pluck('folder'),
            'stats' => ['total' => (clone $visible)->count(), 'size' => (int) (clone $visible)->sum('size'), 'attached' => (clone $visible)->whereNotNull('mediable_type')->count()],
        ]);
    }

    public function store(StoreMediaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user('admin');
        $owner = ($data['owner_type'] ?? null) === 'employee' ? Employee::findOrFail($data['owner_id']) : null;
        abort_unless($owner || $actor->can('media.view'), 403);
        $this->images->authorizeOwner($actor, $owner, true);
        $this->images->upload($request->file('files'), $actor, $owner, $data['collection'], $data['folder']);

        return back()->with('success', count($data['files']) === 1 ? 'Image uploaded.' : count($data['files']).' images uploaded.');
    }

    public function file(Request $request, Media $media): StreamedResponse
    {
        $actor = $request->user('admin');
        $owner = $media->mediable;
        abort_if($media->mediable_type && ! $owner, 404);
        $this->images->authorizeOwner($actor, $owner);
        abort_unless($owner || $actor->can('media.view'), 403);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, null, ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $this->authorizeMutation($request, $media);
        $this->images->update($media, $request->safe()->only(['name', 'alt', 'folder']), $request->file('file'));

        return back()->with('success', 'Image updated.');
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user('admin')->can('media.delete'), 403);
        $this->authorizeMutation($request, $media);
        $this->images->delete($media);

        return back()->with('success', 'Image deleted.');
    }

    private function authorizeMutation(Request $request, Media $media): void
    {
        $owner = $media->mediable;
        abort_if($media->mediable_type && ! $owner, 404);
        $this->images->authorizeOwner($request->user('admin'), $owner, true);
        abort_unless($owner || $request->user('admin')->can('media.view'), 403);
    }
}
