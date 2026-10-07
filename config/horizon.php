<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If the
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that isn't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server to avoid any collisions.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'ppob_backend'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply use the "web" group.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
        'redis:transactions' => 60,
        'redis:supplier_telkomsel' => 30,
        'redis:supplier_indosat' => 30,
        'redis:supplier_xl' => 30,
        'redis:supplier_pln' => 30,
        'redis:supplier_pdam' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list of
    | completed jobs within the Horizon dashboard.
    |
    */

    'silenced' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration (ADR-002: Multi-Supplier Named Queues)
    |--------------------------------------------------------------------------
    |
    | Pengaturan supervisor worker per supplier sesuai kapasitas masing-masing:
    | - Telkomsel: hingga 100 workers (kapasitas tertinggi)
    | - Indosat: hingga 50 workers
    | - XL: hingga 50 workers
    | - PLN: 30 workers (prepaid & postpaid)
    | - PDAM: 20 workers
    | - Default: 10 workers (inquiry & internal jobs)
    |
    */

    'environments' => [
        'production' => [
            'supervisor-telkomsel' => [
                'connection' => 'redis',
                'queue' => ['supplier_telkomsel'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => (int) env('HORIZON_TSEL_WORKERS', 100),
                'minProcesses' => 5,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-indosat' => [
                'connection' => 'redis',
                'queue' => ['supplier_indosat'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => (int) env('HORIZON_ISAT_WORKERS', 50),
                'minProcesses' => 3,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-xl' => [
                'connection' => 'redis',
                'queue' => ['supplier_xl'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => (int) env('HORIZON_XL_WORKERS', 50),
                'minProcesses' => 3,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-pln' => [
                'connection' => 'redis',
                'queue' => ['supplier_pln'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => (int) env('HORIZON_PLN_WORKERS', 30),
                'minProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-pdam' => [
                'connection' => 'redis',
                'queue' => ['supplier_pdam'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => (int) env('HORIZON_PDAM_WORKERS', 20),
                'minProcesses' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'transactions'],
                'balance' => 'simple',
                'processes' => 10,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-telkomsel' => [
                'connection' => 'redis',
                'queue' => ['supplier_telkomsel'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-indosat' => [
                'connection' => 'redis',
                'queue' => ['supplier_indosat'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-xl' => [
                'connection' => 'redis',
                'queue' => ['supplier_xl'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-pln' => [
                'connection' => 'redis',
                'queue' => ['supplier_pln'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-pdam' => [
                'connection' => 'redis',
                'queue' => ['supplier_pdam'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'transactions'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 60,
            ],
        ],
    ],
];
