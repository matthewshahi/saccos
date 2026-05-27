<!DOCTYPE html>
<html lang="en" dir="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    @php
        /*
        |--------------------------------------------------------------------------
        | Base Entities
        |--------------------------------------------------------------------------
        | $companyName is the SACCO name, for example: iSave Sacco.
        | Shahi Services is only the portal/provider reference.
        */
        $companyName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
        $providerName = 'Shahi Services';
        $providerUrl = 'https://shahi.co.ke';

        $routeName = Route::currentRouteName();
        $isGetRequest = request()->isMethod('get');

        /*
        |--------------------------------------------------------------------------
        | Public SEO Routes From web.php
        |--------------------------------------------------------------------------
        | These are the public-facing pages that can safely be indexed.
        | Login, dashboard, member/admin pages and POST actions remain noindex.
        */
        $publicApplicationRouteNames = [
            'register.form',
            'register.unique',
        ];

        $publicLoanRouteNames = [
            'loans.calculator',
            'loans.types.list',
            'loans.types.list1',
            'loan.details',
            'loan.calculator',
        ];

        $isPublicApplicationPage = $isGetRequest && in_array($routeName, $publicApplicationRouteNames, true);
        $isPublicLoanPage = $isGetRequest && in_array($routeName, $publicLoanRouteNames, true);
        $isPublicSeoPage = $isPublicApplicationPage || $isPublicLoanPage;

        /*
        |--------------------------------------------------------------------------
        | Canonical URL
        |--------------------------------------------------------------------------
        | Uses APP_URL instead of url()->current() so production never outputs
        | http://127.0.0.1:8000 when APP_URL is correctly set.
        */
        $configuredBaseUrl = rtrim((string) config('app.url', url('/')), '/');
        $configuredBaseUrl = preg_replace('#^http://#i', 'https://', $configuredBaseUrl);

        $currentPath = '/' . ltrim(request()->path(), '/');
        $defaultCanonicalUrl = $configuredBaseUrl . ($currentPath === '/.' ? '/' : $currentPath);

        /*
        |--------------------------------------------------------------------------
        | Duplicate Public Loan List Canonical
        |--------------------------------------------------------------------------
        | /public/loans/types/list duplicates /loans/types/list, so canonicalise
        | it to the cleaner primary public URL.
        */
        if ($routeName === 'loans.types.list1') {
            $defaultCanonicalUrl = $configuredBaseUrl . '/loans/types/list';
        }

        $canonicalUrl = trim($__env->yieldContent('canonical_url')) ?: $defaultCanonicalUrl;
        $canonicalUrl = preg_replace('#^http://#i', 'https://', $canonicalUrl);

        /*
        |--------------------------------------------------------------------------
        | Default SEO Title
        |--------------------------------------------------------------------------
        */
        if ($isPublicApplicationPage) {
            $defaultSeoTitle = 'Apply to Join ' . $companyName . ' Online | SACCO Membership Application in Kenya';
        } elseif ($routeName === 'loans.calculator' || $routeName === 'loan.calculator') {
            $defaultSeoTitle = $companyName . ' SACCO Loan Calculator | Estimate Loan Repayments Online';
        } elseif ($routeName === 'loans.types.list' || $routeName === 'loans.types.list1') {
            $defaultSeoTitle = $companyName . ' SACCO Loan Products | Loan Types and Calculator';
        } elseif ($routeName === 'loan.details') {
            $defaultSeoTitle = $companyName . ' SACCO Loan Details | Loan Requirements and Calculator';
        } elseif ($routeName === 'login') {
            $defaultSeoTitle = $companyName . ' Member Login';
        } else {
            $defaultSeoTitle = $companyName . ' | v' . date('Y');
        }

        /*
        |--------------------------------------------------------------------------
        | Default SEO Description
        |--------------------------------------------------------------------------
        | Public pages carry useful search text.
        | Internal pages are generic and noindex.
        */
        if ($isPublicApplicationPage) {
            $defaultSeoDescription = 'Apply online to join ' . $companyName . '. Complete your SACCO membership application, submit personal details, next of kin information, supporting documents and registration fee details through a secure portal provided by Shahi Services.';
        } elseif ($isPublicLoanPage) {
            $defaultSeoDescription = 'View ' . $companyName . ' SACCO loan products, loan types, loan requirements and online loan calculator details. Estimate repayments and review available member loan options through a secure portal provided by Shahi Services.';
        } elseif ($routeName === 'login') {
            $defaultSeoDescription = $companyName . ' member login portal for accessing SACCO savings, loans, statements, contributions and member services.';
        } else {
            $defaultSeoDescription = $companyName . ' member portal for SACCO savings, loans, contributions, statements and member services.';
        }

        $seoTitle = trim($__env->yieldContent('seo_title')) ?: $defaultSeoTitle;
        $seoDescription = trim($__env->yieldContent('seo_description')) ?: $defaultSeoDescription;

        /*
        |--------------------------------------------------------------------------
        | Robots
        |--------------------------------------------------------------------------
        | Only public GET application/loan pages are indexable by default.
        | Everything else is noindex unless a child view explicitly overrides it.
        */
        $robotsContent = trim($__env->yieldContent('robots')) ?: (
            $isPublicSeoPage
                ? 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1'
                : 'noindex, nofollow'
        );

        /*
        |--------------------------------------------------------------------------
        | Social Image
        |--------------------------------------------------------------------------
        */
        $defaultOgImage = trim($__env->yieldContent('seo_image')) ?: asset('dist-assets/images/logo.png');
        $defaultOgImage = preg_replace('#^http://#i', 'https://', $defaultOgImage);

        /*
        |--------------------------------------------------------------------------
        | Schema
        |--------------------------------------------------------------------------
        | If a child view provides @section('seo_schema'), it will be used.
        | Otherwise, public pages get a safe default schema.
        */
        $hasCustomSchema = trim($__env->yieldContent('seo_schema')) !== '';

        $defaultSchemaGraph = null;

        if ($isPublicApplicationPage) {
            $defaultSchemaGraph = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $canonicalUrl . '#webpage',
                        'url' => $canonicalUrl,
                        'name' => $seoTitle,
                        'description' => $seoDescription,
                        'inLanguage' => 'en-KE',
                        'about' => [
                            '@id' => $canonicalUrl . '#sacco',
                        ],
                        'provider' => [
                            '@id' => $providerUrl . '#organization',
                        ],
                    ],
                    [
                        '@type' => 'FinancialService',
                        '@id' => $canonicalUrl . '#sacco',
                        'name' => $companyName,
                        'url' => $canonicalUrl,
                        'areaServed' => [
                            '@type' => 'Country',
                            'name' => 'Kenya',
                        ],
                        'serviceType' => [
                            'SACCO membership application',
                            'SACCO member registration',
                            'Online SACCO application',
                        ],
                    ],
                    [
                        '@type' => 'Organization',
                        '@id' => $providerUrl . '#organization',
                        'name' => $providerName,
                        'url' => $providerUrl,
                    ],
                ],
            ];
        } elseif ($isPublicLoanPage) {
            $defaultSchemaGraph = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $canonicalUrl . '#webpage',
                        'url' => $canonicalUrl,
                        'name' => $seoTitle,
                        'description' => $seoDescription,
                        'inLanguage' => 'en-KE',
                        'about' => [
                            '@id' => $canonicalUrl . '#sacco-loans',
                        ],
                        'provider' => [
                            '@id' => $providerUrl . '#organization',
                        ],
                    ],
                    [
                        '@type' => 'FinancialProduct',
                        '@id' => $canonicalUrl . '#sacco-loans',
                        'name' => $companyName . ' SACCO Loan Products',
                        'description' => 'SACCO loan products, loan details and calculator information for ' . $companyName . '.',
                        'provider' => [
                            '@type' => 'FinancialService',
                            'name' => $companyName,
                            'areaServed' => [
                                '@type' => 'Country',
                                'name' => 'Kenya',
                            ],
                        ],
                    ],
                    [
                        '@type' => 'Organization',
                        '@id' => $providerUrl . '#organization',
                        'name' => $providerName,
                        'url' => $providerUrl,
                    ],
                ],
            ];
        }
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $robotsContent }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_KE">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $companyName }}">
    <meta property="og:image" content="{{ $defaultOgImage }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $defaultOgImage }}">

    @if($isPublicSeoPage && ! $hasCustomSchema && ! empty($defaultSchemaGraph))
        <script type="application/ld+json">
{!! json_encode($defaultSchemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
    @endif

    @yield('seo_schema')

    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('dist-assets/css/themes/lite-purple.css?123') }}">
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/perfect-scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/fontawesome-5.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/metisMenu.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/datatables.min.css') }}">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,400i,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('dist-assets/css/logo.css?x=4') }}">

    @if (env('GA_ANALYTICS'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ env('GA_ANALYTICS') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }

            gtag('js', new Date());
            gtag('config', "{{ env('GA_ANALYTICS') }}");
        </script>
    @endif

    @stack('head')
</head>

<body class="text-start">
    @include('partials.period_alert')
    @include('partials.password_change')


    @php $isLoginRoute = Route::currentRouteName() === 'login'; @endphp
    <div class="app-admin-wrap {{ $isLoginRoute ? '' : 'layout-sidebar-vertical sidebar-full' }}">



        @php
            $isLoginRoute = Route::currentRouteName() === 'login';
        @endphp

        @php $isLoginRoute = Route::currentRouteName() === 'login'; @endphp
        <div class="app-admin-wrap {{ $isLoginRoute ? '' : 'layout-sidebar-vertical sidebar-full' }}">

            @if (!$isLoginRoute)
                @include('partials.header')

                @php
                    $viewAsMember = request()->query('view_as_member') === 'y';
                    $pos = Auth::check() ? (int) Auth::user()->member_position : null;
                @endphp

                @if (Auth::check())
                    {{-- FORCE member menu when ?view_as_member=y for member_position 1 or 2 --}}
                    @if ($viewAsMember && in_array($pos, [1, 2], true))
                        @include('partials.menu_members')

                        {{-- Normal logic --}}
                    @elseif ($pos === 2)
                        @include('partials.menu')
                    @elseif ($pos === 1)
                        @include('partials.menu_members')
                    @else
                        @include('partials.menu_public')
                    @endif
                @else
                    @include('partials.menu_public')
                @endif
            @endif


            <div class="main-content-wrap mobile-menu-content bg-off-white m-0" style="padding: 3px;">

                <div class="main-content pt-4">
                    @yield('content')
                </div>
                @include('partials.footer')
            </div>
        </div>


</body>

</html>
