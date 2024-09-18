@extends('layouts.app')

@section('title', 'Выберите каталог')
   
@section('content')


<div id="search-catalog-main-container" class="container">
    @include('components.header')
    <div id="search-result-container-header">
        Результаты поиска
    </div>
    <div id="search-result-main-container">
        
        @foreach ($finalArr as $index => $part)
        <form method="post" action="{{ route('getPart') }}">
            @csrf
        <button type="submit">
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
                </div>
            </div>
        </button>
        </form>
        @endforeach
    </div>
</div>
@endsection