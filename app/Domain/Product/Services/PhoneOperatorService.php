<?php

namespace App\Domain\Product\Services;

class PhoneOperatorService
{
    /**
     * Pemetaan prefix nomor HP operator seluler Indonesia ke kode provider.
     */
    private const PREFIX_MAP = [
        // Telkomsel (Halo, SimPATI, Kartu AS, By.U)
        '0811' => 'telkomsel',
        '0812' => 'telkomsel',
        '0813' => 'telkomsel',
        '0821' => 'telkomsel',
        '0822' => 'telkomsel',
        '0823' => 'telkomsel',
        '0851' => 'telkomsel',
        '0852' => 'telkomsel',
        '0853' => 'telkomsel',

        // Indosat Ooredoo (Matrix, Mentari, IM3)
        '0814' => 'indosat',
        '0815' => 'indosat',
        '0816' => 'indosat',
        '0855' => 'indosat',
        '0856' => 'indosat',
        '0857' => 'indosat',
        '0858' => 'indosat',

        // XL Axiata
        '0817' => 'xl',
        '0818' => 'xl',
        '0819' => 'xl',
        '0859' => 'xl',
        '0877' => 'xl',
        '0878' => 'xl',

        // Axis (bagian dari XL Axiata)
        '0831' => 'xl',
        '0832' => 'xl',
        '0833' => 'xl',
        '0838' => 'xl',

        // Tri (Three)
        '0895' => 'tri',
        '0896' => 'tri',
        '0897' => 'tri',
        '0898' => 'tri',
        '0899' => 'tri',

        // Smartfren
        '0881' => 'smartfren',
        '0882' => 'smartfren',
        '0883' => 'smartfren',
        '0884' => 'smartfren',
        '0885' => 'smartfren',
        '0886' => 'smartfren',
        '0887' => 'smartfren',
        '0888' => 'smartfren',
        '0889' => 'smartfren',
    ];

    /**
     * Normalisasi format nomor HP menjadi format standar '08xxxxxxxxxx'.
     */
    public function normalize(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '62')) {
            $cleaned = '0'.substr($cleaned, 2);
        }

        return $cleaned;
    }

    /**
     * Ambil 4 digit prefix nomor HP.
     */
    public function getPrefix(string $phone): ?string
    {
        $normalized = $this->normalize($phone);

        if (strlen($normalized) < 4) {
            return null;
        }

        return substr($normalized, 0, 4);
    }

    /**
     * Deteksi kode operator berdasarkan prefix nomor HP.
     */
    public function detectOperator(string $phone): ?string
    {
        $prefix = $this->getPrefix($phone);

        if (! $prefix) {
            return null;
        }

        return self::PREFIX_MAP[$prefix] ?? null;
    }
}
