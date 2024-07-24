<!DOCTYPE html>
<html lang="en" dir="">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>iSacco | v<?php echo(date('Y')) ?></title>
    <link rel="stylesheet" href="{{ asset('dist-assets/css/themes/lite-purple.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/fontawesome-5.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/metisMenu.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('dist-assets/css/plugins/datatables.min.css') }}" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,400i,600,700,800,900" rel="stylesheet" />


    
</head>

<body class="text-start">
    <div class="app-admin-wrap layout-sidebar-vertical sidebar-full">
        @include('partials.menu')
        <div class="main-content-wrap mobile-menu-content bg-off-white m-0">
            @include('partials.header')
            <div class="main-content pt-4">
                @yield('content')
            </div>
            @include('partials.footer')
        </div>
    </div>
   
     
</body>
</html>
