<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    | Driver OTP yang digunakan untuk mengirim kode verifikasi via WhatsApp.
    | Pilihan: 'fonnte' | 'wabiz'
    */
    'otp' => [
        'driver' => env('OTP_DRIVER', 'fonnte'),

        'fonnte' => [
            'token' => env('FONNTE_TOKEN'),
            'api_url' => env('FONNTE_API_URL', 'https://api.fonnte.com/send'),
        ],

        'wabiz' => [
            'api_url' => env('WABIZ_API_URL'),
            'token' => env('WABIZ_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    | Driver payment gateway untuk top-up wallet.
    | Pilihan: 'midtrans' | 'xendit'
    */
    'payment_gateways' => [
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'is_sandbox' => env('MIDTRANS_IS_SANDBOX', true),
            'api_url' => env('MIDTRANS_API_URL', 'https://api.midtrans.com'),
        ],

        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'api_url' => env('XENDIT_API_URL', 'https://api.xendit.co'),
            'webhook_key' => env('XENDIT_WEBHOOK_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PPOB Provider Configuration
    |--------------------------------------------------------------------------
    | Konfigurasi webhook secret per provider untuk validasi HMAC.
    | Credential API provider disimpan terenkripsi di tabel 'providers'.
    */
    'providers' => [
        'telkomsel' => [
            'webhook_secret' => env('PPOB_TELKOMSEL_WEBHOOK_SECRET'),
        ],
        'indosat' => [
            'webhook_secret' => env('PPOB_INDOSAT_WEBHOOK_SECRET'),
        ],
        'xl' => [
            'webhook_secret' => env('PPOB_XL_WEBHOOK_SECRET'),
        ],
        'pln' => [
            'webhook_secret' => env('PPOB_PLN_WEBHOOK_SECRET'),
        ],
        'pdam' => [
            'webhook_secret' => env('PPOB_PDAM_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    */
    'transaction' => [
        'idempotency_key_ttl_days' => 30, // Hari sebelum idempotency key dibersihkan
    ],

    /*
    |--------------------------------------------------------------------------
    | Inquiry Settings
    |--------------------------------------------------------------------------
    */
    'inquiry' => [
        'expire_minutes' => 10, // Inquiry kedaluwarsa setelah 10 menit
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Queue Configuration (ADR-002)
    |--------------------------------------------------------------------------
    | Pemetaan queue Horizon & worker limit per supplier PPOB.
    */
    'queues' => [
        'default' => 'transactions',
        'suppliers' => [
            'telkomsel' => [
                'queue' => 'supplier_telkomsel',
                'max_workers' => 100,
                'timeout' => 60,
            ],
            'indosat' => [
                'queue' => 'supplier_indosat',
                'max_workers' => 50,
                'timeout' => 60,
            ],
            'xl' => [
                'queue' => 'supplier_xl',
                'max_workers' => 50,
                'timeout' => 60,
            ],
            'pln' => [
                'queue' => 'supplier_pln',
                'max_workers' => 30,
                'timeout' => 60,
            ],
            'pdam' => [
                'queue' => 'supplier_pdam',
                'max_workers' => 20,
                'timeout' => 60,
            ],
        ],
    ],

];
