<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTierPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'tier_prices' => ['required', 'array', 'min:1'],
            'tier_prices.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Product|null $product */
            $product = $this->route('product');
            if (! $product instanceof Product) {
                $product = Product::find($product);
            }

            if (! $product) {
                return;
            }

            $baseCostCents = $product->base_price_cents ? $product->base_price_cents->toCents() : 0;
            $tierPrices = $this->input('tier_prices', []);

            foreach ($tierPrices as $tierId => $priceRupiah) {
                $sellPriceCents = (int) round(((float) $priceRupiah) * 100);

                if ($sellPriceCents < $baseCostCents) {
                    $validator->errors()->add(
                        "tier_prices.{$tierId}",
                        'Harga jual tier (Rp '.number_format((float) $priceRupiah, 0, ',', '.').") tidak boleh lebih rendah dari modal provider ({$product->base_price_cents?->format()})."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'tier_prices.required' => 'Matriks harga tier wajib dikirim.',
            'tier_prices.*.required' => 'Harga jual tiap tier wajib diisi.',
            'tier_prices.*.numeric' => 'Harga jual harus berupa angka valid.',
            'tier_prices.*.min' => 'Harga jual tidak boleh bernilai negatif.',
        ];
    }
}
