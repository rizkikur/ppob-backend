<?php

namespace App\Domain\Admin\Http\Controllers\Product;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Domain\Product\Models\ProductCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCategoryController extends Controller
{
    public function __construct(
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Display category listing and management.
     */
    public function index(): View
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('admin.products.categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:30', 'unique:product_categories,code'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $category = ProductCategory::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'category.create',
            target: $category,
            changes: ['code' => $category->code, 'name' => $category->name],
            reason: "Tambah kategori {$category->name}"
        );

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$category->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:30', 'unique:product_categories,code,'.$category->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $category->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'category.update',
            target: $category,
            changes: ['code' => $category->code, 'name' => $category->name],
            reason: "Perbarui kategori {$category->name}"
        );

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$category->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified category.
     */
    public function destroy(ProductCategory $category): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        if ($category->products()->count() > 0) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', "Kategori {$category->name} tidak dapat dihapus karena masih memiliki produk terkait.");
        }

        $name = $category->name;
        $category->delete();

        $this->auditLogService->log(
            admin: $admin,
            action: 'category.delete',
            target: $category,
            changes: ['name' => $name],
            reason: "Hapus kategori {$name}"
        );

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$name} berhasil dihapus.");
    }
}
