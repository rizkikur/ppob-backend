<?php

namespace App\Domain\Admin\Http\Controllers\Provider;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProviderManagementService;
use App\Domain\Ppob\Services\ProviderErrorMappingService;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use App\Domain\Product\Models\SystemResponseCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminErrorMappingController extends Controller
{
    public function __construct(
        protected AdminProviderManagementService $providerService
    ) {}

    /**
     * Tampilkan halaman Kamus Response Code (RC) & Seluruh Pemetaan Error Provider.
     */
    public function index(Request $request): View
    {
        $standardErrors = ProviderErrorMappingService::getStandardErrorDefinitions();
        $systemCodes = SystemResponseCode::orderBy('rc', 'asc')->get();
        $providers = Provider::orderBy('name', 'asc')->get();

        $query = ProviderErrorMapping::query()
            ->with('provider')
            ->orderBy('provider_id', 'asc')
            ->orderBy('upstream_code', 'asc');

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        if ($request->filled('internal_code')) {
            $query->where('internal_code', $request->input('internal_code'));
        }

        if ($request->filled('is_retryable')) {
            $query->where('is_retryable', (bool) $request->input('is_retryable'));
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('upstream_code', 'like', "%{$s}%")
                    ->orWhere('custom_message', 'like', "%{$s}%")
                    ->orWhereHas('provider', function ($pq) use ($s) {
                        $pq->where('name', 'like', "%{$s}%")
                            ->orWhere('code', 'like', "%{$s}%");
                    });
            });
        }

        $mappings = $query->paginate(30)->withQueryString();

        // Hitung total mapping per kode standar
        $countsByInternal = ProviderErrorMapping::query()
            ->selectRaw('internal_code, count(*) as total')
            ->groupBy('internal_code')
            ->pluck('total', 'internal_code')
            ->toArray();

        return view('admin.providers.error_mappings_index', [
            'standardErrors' => $standardErrors,
            'systemCodes' => $systemCodes,
            'providers' => $providers,
            'mappings' => $mappings,
            'countsByInternal' => $countsByInternal,
            'totalMappings' => ProviderErrorMapping::count(),
        ]);
    }

    /**
     * Simpan pemetaan Response Code (RC) baru untuk supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $validInternalCodes = array_keys(ProviderErrorMappingService::getStandardErrorDefinitions());

        $validated = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'upstream_code' => ['required', 'string', 'max:50'],
            'internal_code' => ['required', 'string', Rule::in($validInternalCodes)],
            'custom_message' => ['nullable', 'string', 'max:255'],
            'is_retryable' => ['nullable', 'boolean'],
        ]);

        $provider = Provider::findOrFail($validated['provider_id']);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->storeErrorMapping($provider, $validated, $admin);
        ProviderErrorMappingService::clearProviderCache($provider);

        return redirect()
            ->route('admin.error_mappings.index', ['provider_id' => $provider->id])
            ->with('success', "Pemetaan RC [{$validated['upstream_code']}] untuk provider {$provider->name} berhasil disimpan.");
    }

    /**
     * Perbarui pemetaan Response Code (RC) supplier yang sudah ada.
     */
    public function update(Request $request, ProviderErrorMapping $mapping): RedirectResponse
    {
        $validInternalCodes = array_keys(ProviderErrorMappingService::getStandardErrorDefinitions());

        $validated = $request->validate([
            'internal_code' => ['required', 'string', Rule::in($validInternalCodes)],
            'custom_message' => ['nullable', 'string', 'max:255'],
            'is_retryable' => ['nullable', 'boolean'],
        ]);

        $validated['upstream_code'] = $mapping->upstream_code;

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->storeErrorMapping($mapping->provider, $validated, $admin);
        ProviderErrorMappingService::clearProviderCache($mapping->provider);

        return redirect()
            ->back()
            ->with('success', "Pemetaan RC [{$mapping->upstream_code}] berhasil diperbarui.");
    }

    /**
     * Hapus pemetaan Response Code (RC) supplier.
     */
    public function destroy(ProviderErrorMapping $mapping): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $code = $mapping->upstream_code;
        $provider = $mapping->provider;

        $this->providerService->deleteErrorMapping($provider, $mapping, $admin);
        ProviderErrorMappingService::clearProviderCache($provider);

        return redirect()
            ->back()
            ->with('success', "Pemetaan RC [{$code}] berhasil dihapus.");
    }

    /**
     * Tambah Standar Kode Response Code (RC) Internal Baru.
     */
    public function storeSystemCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rc' => ['required', 'string', 'max:10', 'unique:system_response_codes,rc'],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', 'unique:system_response_codes,slug'],
            'name' => ['required', 'string', 'max:100'],
            'status_type' => ['required', 'string', 'in:SUCCESS,FAILED,PENDING'],
            'default_message' => ['required', 'string', 'max:255'],
            'is_retryable' => ['nullable', 'boolean'],
        ]);

        $validated['is_retryable'] = (bool) ($request->input('is_retryable', false));
        $validated['is_system'] = false;

        SystemResponseCode::create($validated);

        // Invalidate Redis cache
        ProviderErrorMappingService::clearSystemCache();

        return redirect()
            ->route('admin.error_mappings.index')
            ->with('success', "Standar Kode Error Internal RC [{$validated['rc']} - {$validated['slug']}] berhasil ditambahkan.");
    }

    /**
     * Update Standar Kode Response Code (RC) Internal.
     */
    public function updateSystemCode(Request $request, SystemResponseCode $code): RedirectResponse
    {
        $validated = $request->validate([
            'rc' => ['required', 'string', 'max:10', Rule::unique('system_response_codes', 'rc')->ignore($code->id)],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('system_response_codes', 'slug')->ignore($code->id)],
            'name' => ['required', 'string', 'max:100'],
            'status_type' => ['required', 'string', 'in:SUCCESS,FAILED,PENDING'],
            'default_message' => ['required', 'string', 'max:255'],
            'is_retryable' => ['nullable', 'boolean'],
        ]);

        $validated['is_retryable'] = (bool) ($request->input('is_retryable', false));

        $code->update($validated);

        // Invalidate Redis cache
        ProviderErrorMappingService::clearSystemCache();

        return redirect()
            ->route('admin.error_mappings.index')
            ->with('success', "Standar Kode Error Internal RC [{$code->rc} - {$code->slug}] berhasil diperbarui.");
    }

    /**
     * Hapus Standar Kode Response Code (RC) Internal.
     */
    public function destroySystemCode(SystemResponseCode $code): RedirectResponse
    {
        if ($code->is_system) {
            return redirect()
                ->route('admin.error_mappings.index')
                ->with('error', "Kode RC [{$code->rc} - {$code->slug}] adalah standar inti sistem dan tidak dapat dihapus.");
        }

        // Cek apakah ada mapping supplier yang masih menggunakan slug ini
        $usedCount = ProviderErrorMapping::where('internal_code', $code->slug)->count();
        if ($usedCount > 0) {
            return redirect()
                ->route('admin.error_mappings.index')
                ->with('error', "Kode RC [{$code->slug}] masih digunakan oleh {$usedCount} aturan mapping supplier. Ubah pemetaan supplier terlebih dahulu sebelum menghapus.");
        }

        $label = "{$code->rc} - {$code->slug}";
        $code->delete();

        // Invalidate Redis cache
        ProviderErrorMappingService::clearSystemCache();

        return redirect()
            ->route('admin.error_mappings.index')
            ->with('success', "Standar Kode Error Internal [{$label}] berhasil dihapus.");
    }
}
