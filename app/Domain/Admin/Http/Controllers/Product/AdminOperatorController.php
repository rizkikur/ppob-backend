<?php

namespace App\Domain\Admin\Http\Controllers\Product;

use App\Domain\Admin\Http\Requests\StoreOperatorRequest;
use App\Domain\Admin\Http\Requests\UpdateOperatorRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Domain\Product\Models\Operator;
use App\Domain\Product\Models\ProductCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperatorController extends Controller
{
    public function __construct(
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of operators.
     */
    public function index(Request $request): View
    {
        $query = Operator::query()
            ->select([
                'id',
                'category_id',
                'name',
                'code',
                'icon_url',
                'prefix_rules',
                'sort_order',
                'is_active',
                'created_at',
            ])
            ->with(['category' => fn ($q) => $q->select(['id', 'name', 'slug'])])
            ->withCount('products');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $operators = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(15)
            ->withQueryString();

        $categories = ProductCategory::query()->select(['id', 'name'])->orderBy('name')->get();

        return view('admin.products.operators.index', [
            'operators' => $operators,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created operator.
     */
    public function store(StoreOperatorRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $prefixes = $this->parsePrefixes($request->input('prefixes') ?? $request->input('prefix_rules'));

        $operator = Operator::create([
            'name' => trim($request->validated('name')),
            'code' => strtoupper(trim($request->validated('code'))),
            'category_id' => $request->validated('category_id'),
            'icon_url' => $request->validated('icon_url'),
            'prefix_rules' => $prefixes,
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) ($request->validated('is_active') ?? true),
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'operator.create',
            target: $operator,
            changes: ['code' => $operator->code, 'name' => $operator->name],
            reason: "Tambah operator baru {$operator->name}"
        );

        return redirect()
            ->route('admin.operators.index')
            ->with('success', "Operator {$operator->name} berhasil ditambahkan.");
    }

    /**
     * Update an existing operator.
     */
    public function update(UpdateOperatorRequest $request, Operator $operator): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $prefixes = $this->parsePrefixes($request->input('prefixes') ?? $request->input('prefix_rules'));

        $operator->update([
            'name' => trim($request->validated('name')),
            'code' => strtoupper(trim($request->validated('code'))),
            'category_id' => $request->validated('category_id'),
            'icon_url' => $request->validated('icon_url'),
            'prefix_rules' => $prefixes,
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) ($request->validated('is_active') ?? false),
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'operator.update',
            target: $operator,
            changes: ['code' => $operator->code, 'name' => $operator->name],
            reason: "Perbarui operator {$operator->name}"
        );

        return redirect()
            ->route('admin.operators.index')
            ->with('success', "Operator {$operator->name} berhasil diperbarui.");
    }

    /**
     * Delete an operator.
     */
    public function destroy(Operator $operator): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $name = $operator->name;
        $operator->delete();

        $this->auditLogService->log(
            admin: $admin,
            action: 'operator.delete',
            target: $operator,
            changes: ['name' => $name],
            reason: "Hapus operator {$name}"
        );

        return redirect()
            ->route('admin.operators.index')
            ->with('success', "Operator {$name} berhasil dihapus.");
    }

    /**
     * Live test prefix detection.
     */
    public function detect(Request $request): JsonResponse
    {
        $number = (string) ($request->input('phone') ?? $request->input('number', ''));
        $detected = Operator::detectByDestinationNumber($number);

        if ($detected) {
            $detected->loadMissing('category');
            return response()->json([
                'success' => true,
                'detected' => true,
                'operator' => [
                    'id' => $detected->id,
                    'name' => $detected->name,
                    'code' => $detected->code,
                    'category' => $detected->category?->name,
                    'icon_url' => $detected->icon_url,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'detected' => false,
            'operator' => null,
            'message' => 'Operator tidak terdeteksi untuk prefix nomor ini.',
        ]);
    }

    /**
     * Helper to parse comma/newline/space separated string or array of prefixes into clean array.
     *
     * @param string|array<int, string>|null $prefixesInput
     * @return array<int, string>|null
     */
    private function parsePrefixes(string|array|null $prefixesInput): ?array
    {
        if (empty($prefixesInput)) {
            return null;
        }

        if (is_array($prefixesInput)) {
            $filtered = array_values(array_filter(array_map('trim', $prefixesInput), fn($p) => $p !== ''));
            return ! empty($filtered) ? $filtered : null;
        }

        $items = preg_split('/[\s,]+/', trim($prefixesInput));
        if ($items === false) {
            return null;
        }

        $filtered = array_values(array_filter(array_map('trim', $items), fn($p) => $p !== ''));

        return ! empty($filtered) ? $filtered : null;
    }
}
