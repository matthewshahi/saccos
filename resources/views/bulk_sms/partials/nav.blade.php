```blade
{{-- resources/views/bulk_sms/partials/nav.blade.php --}}

@php
    /*
    |--------------------------------------------------------------------------
    | Bulk SMS Navigation Partial
    |--------------------------------------------------------------------------
    | Shared navigation for all Bulk SMS module pages.
    | This partial does not depend on controller data.
    |--------------------------------------------------------------------------
    */

    $bulkSmsNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'bulk_sms.index',
            'icon' => 'i-Bar-Chart',
            'active' => request()->routeIs('bulk_sms.index'),
        ],
        [
            'label' => 'Send Bulk SMS',
            'route' => 'bulk_sms.compose',
            'icon' => 'i-Mail-Send',
            'active' => request()->routeIs('bulk_sms.compose*'),
        ],
        [
            'label' => 'Custom Send',
            'route' => 'bulk_sms.custom_send',
            'icon' => 'i-Phone-SMS',
            'active' => request()->routeIs('bulk_sms.custom_send*'),
        ],
        [
            'label' => 'Settings',
            'route' => 'bulk_sms.settings',
            'icon' => 'i-Gear-2',
            'active' => request()->routeIs('bulk_sms.settings*'),
        ],
        [
            'label' => 'Providers',
            'route' => 'bulk_sms.providers',
            'icon' => 'i-Network',
            'active' => request()->routeIs('bulk_sms.providers*')
                || request()->routeIs('bulk_sms.provider_configs*')
                || request()->routeIs('bulk_sms.provider_networks*'),
        ],
        [
            'label' => 'Messages',
            'route' => 'bulk_sms.messages',
            'icon' => 'i-Speach-Bubble-3',
            'active' => request()->routeIs('bulk_sms.messages*'),
        ],
        [
            'label' => 'Test SMS',
            'route' => 'bulk_sms.test',
            'icon' => 'i-Mail-2',
            'active' => request()->routeIs('bulk_sms.test*'),
        ],
        [
            'label' => 'Diagnostics',
            'route' => 'bulk_sms.diagnostics',
            'icon' => 'i-Check',
            'active' => request()->routeIs('bulk_sms.diagnostics*'),
        ],
        [
            'label' => 'Reports',
            'route' => 'bulk_sms.reports.summary',
            'icon' => 'i-File-Chart',
            'active' => request()->routeIs('bulk_sms.reports*'),
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Optional Provider Context
    |--------------------------------------------------------------------------
    | These extra buttons appear only on provider-specific pages where
    | $providerRow is available.
    |--------------------------------------------------------------------------
    */

    $currentProviderCode = null;

    if (
        isset($providerRow)
        && !empty($providerRow->provider_code)
    ) {
        $currentProviderCode = $providerRow->provider_code;
    }
@endphp

<style>
    .bulk-sms-module-nav {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }

    .bulk-sms-module-nav .card-body {
        padding: 10px 12px 6px 12px;
    }

    .bulk-sms-module-nav .bulk-sms-nav-btn {
        margin-right: 6px;
        margin-bottom: 6px;
        border-radius: 6px;
        font-size: 13px;
        padding: 6px 10px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .bulk-sms-module-nav .bulk-sms-nav-label {
        font-weight: 500;
    }

    .bulk-sms-module-nav .bulk-sms-context-title {
        font-size: 12px;
        color: #6c757d;
        margin-right: 8px;
        margin-bottom: 6px;
        display: inline-flex;
        align-items: center;
    }

    @media (max-width: 768px) {
        .bulk-sms-module-nav .bulk-sms-nav-btn {
            width: 100%;
            justify-content: flex-start;
            margin-right: 0;
        }

        .bulk-sms-module-nav .bulk-sms-context-title {
            width: 100%;
            margin-top: 4px;
        }
    }
</style>

<div class="card mb-4 bulk-sms-module-nav">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center">

            @foreach ($bulkSmsNavItems as $item)
                @if (Route::has($item['route']))
                    <a
                        href="{{ route($item['route']) }}"
                        class="btn btn-sm bulk-sms-nav-btn {{ $item['active'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                    >
                        <i class="nav-icon {{ $item['icon'] }}"></i>

                        <span class="bulk-sms-nav-label">
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endif
            @endforeach

            @if ($currentProviderCode)
                <span class="bulk-sms-context-title">
                    Provider:
                </span>

                @if (Route::has('bulk_sms.providers.edit'))
                    <a
                        href="{{ route('bulk_sms.providers.edit', $currentProviderCode) }}"
                        class="btn btn-sm bulk-sms-nav-btn {{ request()->routeIs('bulk_sms.providers.edit') ? 'btn-primary' : 'btn-outline-success' }}"
                    >
                        <i class="nav-icon i-Pen-2"></i>

                        <span class="bulk-sms-nav-label">
                            Edit Provider
                        </span>
                    </a>
                @endif

                @if (Route::has('bulk_sms.provider_configs'))
                    <a
                        href="{{ route('bulk_sms.provider_configs', $currentProviderCode) }}"
                        class="btn btn-sm bulk-sms-nav-btn {{ request()->routeIs('bulk_sms.provider_configs*') ? 'btn-primary' : 'btn-outline-primary' }}"
                    >
                        <i class="nav-icon i-Gear"></i>

                        <span class="bulk-sms-nav-label">
                            Configs
                        </span>
                    </a>
                @endif

                @if (Route::has('bulk_sms.provider_networks'))
                    <a
                        href="{{ route('bulk_sms.provider_networks', $currentProviderCode) }}"
                        class="btn btn-sm bulk-sms-nav-btn {{ request()->routeIs('bulk_sms.provider_networks*') ? 'btn-primary' : 'btn-outline-info' }}"
                    >
                        <i class="nav-icon i-Internet"></i>

                        <span class="bulk-sms-nav-label">
                            Networks
                        </span>
                    </a>
                @endif
            @endif

        </div>
    </div>
</div>

{{-- Bulk SMS flash messages --}}
@if (session('success'))
    <div
        class="alert alert-success alert-dismissible fade show mb-3"
        role="alert"
    >
        <strong>Success:</strong>
        {{ session('success') }}

        <button
            type="button"
            class="close"
            data-dismiss="alert"
            aria-label="Close"
        >
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if (session('error'))
    <div
        class="alert alert-danger alert-dismissible fade show mb-3"
        role="alert"
    >
        <strong>Error:</strong>
        {{ session('error') }}

        <button
            type="button"
            class="close"
            data-dismiss="alert"
            aria-label="Close"
        >
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if (session('warning'))
    <div
        class="alert alert-warning alert-dismissible fade show mb-3"
        role="alert"
    >
        <strong>Warning:</strong>
        {{ session('warning') }}

        <button
            type="button"
            class="close"
            data-dismiss="alert"
            aria-label="Close"
        >
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif
```
