<?php

namespace App\Http\Controllers;

use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageRevision;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsPageController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogs) {}

    public function index(Request $request): JsonResponse
    {
        $pages = CmsPage::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->input('search'));
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->with('creator:id,name')
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json($pages);
    }

    public function show(CmsPage $cmsPage): JsonResponse
    {
        return response()->json(['data' => $cmsPage]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: Str::slug($data['title']));
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        if (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $page = CmsPage::create($data);
        $this->record($request, 'cms_page.created', $page, null, $page->only(['title', 'slug', 'status']));

        return response()->json(['data' => $page], 201);
    }

    public function update(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $data = $this->validated($request, $cmsPage);
        if (! isset($data['slug']) || $data['slug'] === '') {
            $data['slug'] = $cmsPage->slug;
        }

        $data['updated_by'] = $request->user()->id;
        if (($data['status'] ?? $cmsPage->status) === 'published' && empty($data['published_at']) && ! $cmsPage->published_at) {
            $data['published_at'] = now();
        }

        $revisionFields = [
            'title', 'slug', 'excerpt', 'content', 'sections', 'featured_image', 'status', 'published_at',
            'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots_index', 'robots_follow',
        ];

        // Fill before checking dirty attributes. Checking isDirty() on the untouched
        // route-bound model silently skipped every automatic revision snapshot.
        [$updatedPage, $old] = DB::transaction(function () use ($cmsPage, $data, $revisionFields, $request): array {
            $page = CmsPage::query()->lockForUpdate()->findOrFail($cmsPage->id);
            $oldValues = $page->only($revisionFields);
            $page->fill($data);

            if ($page->isDirty()) {
                $version = ((int) $page->revisions()->max('version')) + 1;

                CmsPageRevision::create([
                    'cms_page_id' => $page->id,
                    'version' => $version,
                    'snapshot' => $oldValues,
                    'created_by' => $request->user()->id,
                    'change_note' => 'Automatic snapshot before update',
                ]);
            }

            $page->save();

            return [$page->fresh(), $oldValues];
        });

        $this->record(
            $request,
            'cms_page.updated',
            $updatedPage,
            $old,
            $updatedPage->only(['title', 'slug', 'status'])
        );

        return response()->json(['data' => $updatedPage]);
    }

    public function destroy(Request $request, CmsPage $cmsPage): JsonResponse
    {
        abort_if($cmsPage->status === 'published', 422, 'Unpublish this page before deleting it.');
        $old = $cmsPage->only(['title', 'slug', 'status']);
        $cmsPage->delete();
        $this->record($request, 'cms_page.deleted', null, $old, null);

        return response()->json(['message' => 'Page deleted.']);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A valid image file is required.');
        $path = $file->store('cms/media', 'public');
        abort_if($path === false, 500, 'The media file could not be stored.');

        try {
            $media = CmsMedia::create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json([
            'data' => [
                'id' => $media->id,
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:1000000'],
            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*' => ['array'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'robots_index' => ['required', 'boolean'],
            'robots_follow' => ['required', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $slug !== '' ? $slug : 'page';
        $candidate = $base;
        $i = 2;

        while (CmsPage::where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function record(Request $request, string $action, ?CmsPage $page, ?array $old, ?array $new): void
    {
        $this->activityLogs->recordForUser(
            user: $request->user(),
            action: $action,
            module: 'content.cms',
            description: 'CMS page '.$action.'.',
            subject: $page,
            oldValues: $old,
            newValues: $new,
        );
    }
}
