<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('categories.view'), 403);

        $module = $request->query('module');
        $type = $request->query('type');
        $search = $request->query('search');

        $categories = Category::with('parent')
            ->withCount('children')
            ->when($module && $module !== 'all', fn ($q) => $q->forModule($module))
            ->when($type && $type !== 'all', fn ($q) => $q->ofType($type))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('COALESCE(parent_id, id) DESC')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        $parentCategories = Category::query()
            ->whereNull('parent_id')
            ->select('id', 'name', 'module', 'type', 'is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/categories/Index', [
            'categories' => CategoryResource::collection($categories),
            'parentCategories' => $parentCategories,
            'filters' => $request->only(['module', 'type', 'search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        if (! empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            if ($parent && ! $parent->is_active) {
                $data['is_active'] = false;
            }
        }

        Category::create([
            ...$data,
            'is_system' => false,
        ]);

        return back()->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        if (! empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            if ($parent && ! $parent->is_active) {
                $data['is_active'] = false;
            }
        }

        $category->update($data);

        return back()->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Category $category)
    {
        abort_unless($request->user('admin')->can('categories.delete'), 403);

        if ($category->is_system) {
            return back()->with('error', 'System categories cannot be deleted.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }
}
