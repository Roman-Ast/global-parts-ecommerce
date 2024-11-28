@extends('layouts.app')

@section('title', 'Взаиморасчеты')
    


@section('content')
    @include('components.header')

    <div id="settlements-container" class="container">
        <div id="settlement-container-header">
            <div id="settlement-container-inner-header">
                Взаиморасчеты
            </div>
            <div id="settlement-container-balanse">
                {{ number_format($balance, 2, '.', ' ') }}
            </div>
        </div>
        <div id="settlement-container-pre-header">
            <div class="settlement-container-sum-realised">
                <div class="sum-realised">Всего заказано:</div>
                <div class="sum-relised-sum">- {{ number_format($sumReleased, 2, '.', ' ') }}</div> 
            </div>
            <div class="settlement-container-sum-realised">
                <div class="sum-realised">Всего оплачено:</div>
                <div class="sum-relised-sum">{{ number_format($sumPaid, 2, '.', ' ') }}</div> 
            </div>
        </div>
        <div id="settlement-item-header">
            <div id="settlement-item-header-date">
                Дата
            </div>
            <div id="settlement-item-header-id">
                Операция
            </div>
            <div id="settlement-item-header-username">
                Контрагент
            </div>
            <div id="settlement-item-header-paid">
                Оплачено
            </div>
            <div id="settlement-item-header-realised">
                Отгружено
            </div>
            <div id="settlement-item-header-sum">
                Сумма
            </div>
        </div>
        @foreach ($settlements as $settlementItem)
        <div class="settlement-item-wrapper">
            <div class="settlement-item-header">
                <div class="settlement-item-date">
                    {{ $settlementItem->date }}
                </div>
                <div class="settlement-item-id">
                    <input type="hidden" class="order_{{ $settlementItem->order_id }}" name="order_id" value="{{ $settlementItem->order_id }}">
                    @if ($settlementItem->operation == 'realization')
                    <a href="#">Реализация товаров №0000{{ $settlementItem->order_id }}</a>
                    @else
                        <div>Оплата</div>
                    @endif
                </div>
                <div class="settlement-item-username">
                    {{ $settlementItem->user->name }}
                </div>
                <div class="settlement-item-operation">
                    @if ($settlementItem->paid)
                        <img src="images/paid-24.png">
                    @endif
                </div>
                <div class="settlement-item-operation">
                    @if ($settlementItem->released)
                        <img src="images/realised-24.png">
                    @endif
                </div>
                <div class="settlement-item-sum">
                    @if ($settlementItem->operation == 'realization')
                    - {{ number_format($settlementItem->sum, 2, '.', ' ') }} 
                    @else
                    {{ number_format($settlementItem->sum, 2, '.', ' ') }}
                    @endif
                </div>
            </div>
            <div class="settlement-item-content">
                <table class="table settlement-item-content-table">
                    <tbody>
                       
                    </tbody>
                 </table>
            </div>
        </div>
        
        @endforeach
    </div>
    
    
    @include('components.footer')
@endsection