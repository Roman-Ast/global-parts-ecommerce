@extends('layouts.app')

@section('title', 'Выберите каталог')
   
@section('content')


<div id="search-catalog-main-container" class="container">
    <div id="shadow">
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" style="width: 6rem; height: 6rem;" role="status">
            <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div id="loading" class="d-flex justify-content-center mt-5 pouring">
            Выполняется проценка складов... это может занять несколько секунд, пожалуйста ожидайте...
        </div>
    </div>
    @include('components.header')
    
    <div id="search-result-container-header">
        Результаты поиска
    </div>
    <div id="search-result-main-container">
        @foreach ($finalArr as $index => $part)
        <form method="post" action="{{ route('getPart') }}" class="form-search-item">
            @csrf
            <button type="submit" class="btn btn-light w-100">
                <div class="catalog-list-item">
                    <div class="catalog-list-item-brand catalog-list-item-cell">
                        {{ $part['brand'] }}
                        <input type="hidden" name="brand" value="{{ $part['brand'] }}">
                    </div>
                    <div class="catalog-list-item-art catalog-list-item-cell">
                        {{ $part['partnumber'] }}
                        <input type="hidden" name="partnumber" value="{{ $part['partnumber'] }}">
                    </div>
                    <div class="catalog-list-item-name catalog-list-item-cell">
                        {{ $part['name'] }}
                        <input type="hidden" name="guid" value="{{ $part['guid'] }}">
                        <input type="hidden" name="rossko_need_to_search" value="{{ $part['rossko_need_to_search'] }}">
                    </div>
                </div>
            </button>
        </form>
        @endforeach
    </div>
</div>

@include('components.footer')
@endsection