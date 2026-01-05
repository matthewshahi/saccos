<!DOCTYPE html>
<html lang="en" dir="">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>{{ $defaultCompanyName }} | v<?php echo (date('Y')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('dist-assets/css/themes/lite-purple.css?123') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/fontawesome-5.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/metisMenu.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/datatables.min.css') }}" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,400i,600,700,800,900" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/logo.css?x=4') }}" />

    <!-- Google tag (gtag.js) -->
    @if(env('GA_ANALYTICS'))
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
<meta name="csrf-token" content="{{ csrf_token() }}">

</head>

<body class="text-start">
@include('partials.period_alert')
@include('partials.password_change')


@php $isLoginRoute = Route::currentRouteName() === 'login'; @endphp
<div class="app-admin-wrap {{ $isLoginRoute ? '' : 'layout-sidebar-vertical sidebar-full' }}">

         

        @php
        $isLoginRoute = Route::currentRouteName() === 'login';
        @endphp

        @if (!$isLoginRoute)
        @include('partials.header')

        @if (Auth::check() && Auth::user()->member_position == 2)
        @include('partials.menu')
        @elseif (Auth::check() && Auth::user()->member_position == 1)
        @include('partials.menu_members')
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