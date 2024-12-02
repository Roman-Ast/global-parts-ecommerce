@extends('layouts.app')

@section('title', 'Панель администратора')
    
@section('content')
    
    <div id="admin-main-container">
        <div id="container-header">
            <a href="/"> Global Parts</a> админ панель вы вошли как: {{ auth()->user()->name }}
        </div>
        <div id="menu">
            <div class="menu-item-container" target="orders">
                <div class="menu-item-img">

                </div>
                <div class="menu-item-name" >
                    Заказы
                </div>
            </div>
            <div class="menu-item-container" target="settlements">
                <div class="menu-item-img">

                </div>
                <div class="menu-item-name">
                    Взаиморасчеты
                </div>
            </div>
            <div class="menu-item-container" target="all-customers">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Клиенты
                </div>
            </div>
            <div class="menu-item-container" target="make-pay">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Оплата клиента
                </div>
            </div>
            <div class="menu-item-container" target="all-payments">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Все оплаты
                </div>
            </div>
            <div class="menu-item-container" target="products_in_office">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Товар в наличии в офисе
                </div>
            </div>
            <div class="menu-item-container" target="supplier_settlements">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Взаиморасчеты с поставщиками
                </div>
            </div>
            <div class="menu-item-container" target="supplier_payments">
                <div class="menu-item-img">
                    
                </div>
                <div class="menu-item-name">
                    Оплата поставщикам
                </div>
            </div>
        </div>
        <div id="content">
            <div id="orders" class="container admin-content-item">
                <div id="orders-filter">
                    <form action="/orders/filter" method="POST" id="orders-filter-bar">
                        @csrf
                        <div id="orders-filter-date" class="order-filter-item">
                            <input type="date" name="filter_date_from" class="input-group input-group-sm">
                            <input type="date" name="filter_date_to" class="input-group input-group-sm">
                        </div>
                        <div id="orders-filter-user" class="order-filter-item">
                            <select name="user">
                                <option selected disabled>Выбери пользователя</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                        </select>
                        </div>
                        <div id="orders-filter-customer" class="order-filter-item">
                            <select name="customer">
                                <option selected disabled>Выбери клиента</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer }}">{{ $customer }}</option>
                                @endforeach
                        </select>
                        </div>
                        <input type="submit" name="" id="" value="применить">
                    </form>
                </div>
                @foreach ($orders as $orderItem)
                <div class="order-item-wrapper">
                    <div class="order-item-header">
                       <div class="order-item-id">
                            0000{{ $orderItem->id }}
                       </div>
                       <div class="order-item-user-name">
                            <span>{{ $orderItem->user->name }}</span> 
                            <span style="font-size: 0.7em">{{ $orderItem->customer_phone }}</span> 
                       </div>
                       <div class="order-item-status">
                            {{ $orderItem->status }} <img src="/images/clock-wait-16.png">
                       </div>
                       <div class="order-item-date">
                            {{ $orderItem->date }}
                       </div>
                       <div class="order-item-time">
                            {{ $orderItem->time }}
                       </div>
                       <div class="order-item-sum">
                        {{ number_format($orderItem->sum, 2, ',', ' ') }}
                   </div>
                    </div>
                    <div class="order-item-products-wrapper">
                        <div class="admin-order-item-products-content-header">
                            <div class="products-content-header-item">Партномер</div>
                            <div class="products-content-header-item">Артикул</div>
                            <div class="products-content-header-item">Брэнд</div>
                            <div class="products-content-header-item">Наименование</div>
                            <div class="products-content-header-item">Кол-во</div>
                            <div class="products-content-header-item">Цена</div>
                            <div class="products-content-header-item">Сумма</div>
                            <div class="products-content-header-item">Склад</div>
                            <div class="products-content-header-item">Доставка</div>
                            <div class="products-content-header-item">Статус</div>
                            
                        </div>
                        @foreach ($orderItem->products as $product)
                        <div class="admin-order-item-products-content">
                            <div class="order-products-searched_number">
                                {{ $product->searched_number }}
                            </div>
                            <div class="order-products-article">
                                {{ $product->article }}
                            </div>
                            <div class="order-products-brand">
                                {{ $product->brand }}
                            </div>
                            <div class="order-products-name">
                                {{ mb_strimwidth($product->name, 0, 50, '...') }}
                            </div>
                            <div class="order-products-qty">
                                {{ $product->qty }}
                            </div>
                            <div class="order-products-price">
                                {{ number_format($product->price, 0, ',', ' ') }}
                            </div>
                            <div class="order-products-item_sum">
                                {{ number_format($product->item_sum, 0, ',', ' ') }}
                            </div>
                            <div class="order-products-fromStock">
                                {{ $product->fromStock }}
                            </div>
                            <div class="order-products-deliveryTime">
                                {{ $product->deliveryTime }}
                            </div>
                            <div class="order-products-status">
                                <select name="order_product_status" class="order_product_status form-select">
                                    @foreach ($statuses as $key => $status)
                                        @if ($key != $product->status)
                                            <option value="{{ $key }}">{{ $status }}</option>
                                        @else
                                            <option value="{{ $key }}" selected disabled>{{ $status }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="change_status">
                                <input type="hidden" value="{{ $product->id }}">
                                <button class="btn btn-sm btn-info change_status_submit">Сменить</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div id="settlements" class="container admin-content-item">
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
                                <a href="#">Реализация товаров №0000{{ $settlementItem->order_id }}</a>
                            </div>
                            <div class="settlement-item-username">
                                {{ $settlementItem->user->name }}
                            </div>
                            <div class="settlement-item-operation">
                                @if ($settlementItem->paid)
                                    <img src="images/cash-24.png">
                                @endif
                            </div>
                            <div class="settlement-item-operation">
                                @if ($settlementItem->released)
                                    <img src="images/realised-24.png">
                                @endif
                            </div>
                            <div class="settlement-item-sum">
                                {{ number_format($settlementItem->sum, 2, '.', ' ') }}
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
            <div id="make-pay" class="container admin-content-item">
                <form id="pay-container" action="/payment" method="POST">
                    @csrf
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Дата
                        </div>
                        <div class="pay-item-container-input">
                            <input type="date" name="date" class="form-control">
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Сумма
                        </div>
                        <div class="pay-item-container-input">
                            <input type="number" name="sum" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Способ оплаты
                        </div>
                        <div class="pay-item-container-input">
                            <select name="payment_method" class="form-control">
                                <option value="empty" selected></option>
                                <option value="kaspi-perevod">Каспи перевод</option>
                                <option value="kaspi-qr">Каспи QR</option>
                                <option value="bank-card">Карта банка</option>
                                <option value="cash">Наличные</option>
                                <option value="cashless">Безнал</option>
                            </select>
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Клиент
                        </div>
                        <div class="pay-item-container-input" >
                            <select name="user_id" class="form-control" name="user_id">
                                <option value="empty" selected></option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Комментарии
                        </div>
                        <div class="pay-item-container-input">
                            <textarea name="comments" cols="30" rows="10" class="form-control"></textarea>
                        </div>
                    </div>
                    <input type="submit" class="btn btn-success" value="Провести оплату">
                </form>
            </div>
            <div id="all-payments" class="container admin-content-item">
                <div id="payment-item-header">
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
                        Сумма
                    </div>
                    <div id="settlement-item-header-paid">
                        Способ оплаты
                    </div>
                    <div id="settlement-item-header-paid">
                        Комментарии
                    </div>
                </div>
                @foreach ($payments as $paymentItem)
                    <div class="payments-item-wrapper">
                        <div class="payments-item-date">
                            {{ $paymentItem->date }}
                        </div>
                        <div class="payments-item-id">
                            <div>Оплата №0000{{ $paymentItem->id }}</div>
                        </div>
                        <div class="payments-item-username">
                            {{ $paymentItem->user->name }}
                        </div>
                        <div class="payments-item-sum">
                            {{ number_format($paymentItem->sum, 2, '.', ' ') }}
                        </div>
                        <div class="payments-item-sum">
                            {{ $paymentItem->payment_method }}
                        </div>
                        <div class="payments-item-comments">
                            {{ $paymentItem->comments }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div id="all-customers" class="container admin-content-item">
                <div id="customers-header">
                    <div class="customers-header-item">
                        #
                    </div>
                    <div class="customers-header-item">
                        Имя
                    </div>
                    <div class="customers-header-item">
                        e-mail
                    </div>
                    <div class="customers-header-item">
                        Телефон
                    </div>
                    <div class="customers-header-item">
                        Статус
                    </div>
                    <div class="customers-header-item">
                        Кол-во заказов
                    </div>
                    <div class="customers-header-item">
                        Сумма заказов
                    </div>
                </div>
                @foreach ($usersCalculating as $id => $userItem)
                    <div class="customer-content">
                        <div class="customer-content-item">
                            {{ $userItem['id'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $userItem['name'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $userItem['email'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $userItem['phone'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $userItem['role'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $userItem['qtyOrders'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ number_format($userItem['sumOrders'], 0, ',', ' ') }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div id="supplier_settlements" class="container admin-content-item">
                <div id="supplier_settlements-header">
                    <div class="supplier_settlements-header-item">
                        <div></div>
                        <div>Заказы</div>
                        <div>Оплата</div>
                        <div>Итог</div>
                    </div>
                    @foreach ($suppliers_debt as $supplierName => $supplierSettlement)
                    <div class="supplier_settlements-header-item">
                        <div class="supplier_settlements-header-item-name">
                            {{ $supplierName }}
                        </div>
                        <div class="supplier_settlements-header-item-sum-order" style="color: red;">
                            {{ $supplierSettlement['ralizationSum'] }}
                        </div>
                        <div class="supplier_settlements-header-item-sum-pay" style="color: green;">
                            {{ $supplierSettlement['pay'] }}
                        </div>
                        <div class="supplier_settlements-header-item-total">
                            @if (($supplierSettlement['pay'] + $supplierSettlement['ralizationSum']) < 0) 
                                <span style="color: red">{{ $supplierSettlement['pay'] + $supplierSettlement['ralizationSum']}}</span>
                            @else 
                                <span style="color: green">{{ $supplierSettlement['pay'] + $supplierSettlement['ralizationSum']}}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    
                </div>
                <div id="customers-header">
                    <div class="customers-header-item">
                        Заказ
                    </div>
                    <div class="customers-header-item">
                        Поставщик
                    </div>
                    <div class="customers-header-item">
                        Сумма
                    </div>
                    <div class="customers-header-item">
                        Дата
                    </div>
                    <div class="customers-header-item">
                        Операция
                    </div>
                </div>
                @foreach ($supplerSettlements as $settlement)
                    <div class="customer-content">
                        <div class="customer-content-item">
                            {{ $settlement['order_id'] }}
                        </div>
                        <div class="customer-content-item">
                            {{ $settlement['supplier'] }}
                        </div>
                        <div class="customer-content-item">
                            @if ($settlement['operation'] == 'realization')
                                <span style="color: red">{{ $settlement['sum'] }}</span>
                            @else
                                <span style="color: green">{{ $settlement['sum'] }}</span>
                            @endif
                        </div>
                        <div class="customer-content-item">
                            {{ $settlement['date'] }}
                        </div>
                        <div class="customer-content-item">
                            @if ($settlement['operation'] == 'realization')
                                <img src="images/realised-24.png">
                            @else
                                <img src="images/cash-24.png">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div id="supplier_payments" class="container admin-content-item">
                <form id="pay-container" action="{{ route('supplier.payment') }}" method="POST">
                    @csrf
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Дата
                        </div>
                        <div class="pay-item-container-input">
                            <input type="date" name="date" class="form-control">
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Сумма
                        </div>
                        <div class="pay-item-container-input">
                            <input type="number" name="sum" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="pay-item-container">
                        <div class="pay-item-container-name">
                            Поставщик
                        </div>
                        <div class="pay-item-container-input">
                            <select name="supplier" class="form-control">
                                <option disabled selected>Выбери поставщика</option>
                                @foreach ($suppliers as $supplierEng => $supplierRus)
                                    <option value="{{ $supplierEng }}">{{ $supplierRus }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="submit" class="btn btn-success" value="Провести оплату">
                </form>
            </div>
        </div>
    </div>
@endsection