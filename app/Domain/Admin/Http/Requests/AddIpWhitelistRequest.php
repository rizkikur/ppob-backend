<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddIpWhitelistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'ip' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $value = trim($value);
                    if ($value === '*') {
                        return;
                    }

                    // Check if CIDR notation (e.g. 192.168.1.0/24)
                    if (str_contains($value, '/')) {
                        [$subnet, $bits] = explode('/', $value, 2);
                        if (! filter_var($subnet, FILTER_VALIDATE_IP) || ! ctype_digit($bits) || (int) $bits < 0 || (int) $bits > 128) {
                            $fail('Format CIDR IP address tidak valid (contoh: 103.10.20.0/24).');
                        }

                        return;
                    }

                    // Check if standard IPv4 or IPv6
                    if (! filter_var($value, FILTER_VALIDATE_IP)) {
                        $fail('Format IP address tidak valid (harus IPv4, IPv6, atau notasi CIDR).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ip.required' => 'Alamat IP wajib diisi.',
        ];
    }
}
