<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <link href="{{ URL::asset('css/components/header.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/partSearchRes.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/main.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/searchCatalog.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/registerForm.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/footer.css') }}" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <script src="{{ URL::asset('js/jquery.min.js') }}"></script>
        <script src="{{ URL::asset('js/main.js') }}"></script>
        <!--<script src="{{ URL::asset('js/actions/getSearch.js') }}" type="text/javascript"></script>-->
        <!-- Styles -->
        <style>

        </style>

    </head>
    <body>

        @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    </body>
</html>















