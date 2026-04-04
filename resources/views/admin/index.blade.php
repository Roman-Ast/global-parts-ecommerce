@extends('layouts.app')

@section('title', 'Панель администратора')
    
@section('content')
    <div id="admin-main-container">
        <div id="container-header">
            <a href="/"> Global Parts</a> админ панель вы вошли как: {{ auth()->user()->name }}
        </div>
        <div id="menu">
            <div class="accordion accordion-flush" id="accordionFlushExample">
                <div class="accordion-item" >
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFirst" aria-expanded="false" aria-controls="flush-collapseFirst">
                            Дашборд
                        </button>
                    </h2>
                    <div id="flush-collapseFirst" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="dashboard">Дашборд</div>
                    </div>
                </div>
                <div class="accordion-item" >
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                            Заказы
                        </button>
                    </h2>
                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="orders">Список заказов</div>
                    </div>
                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="manually-order">Создать заказ</div>
                    </div>
                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="make_customer_return">Создать возврат</div>
                    </div>
                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="customer_returns_list">Список возвратов</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                            Движение денежных средств
                        </button>
                    </h2>
                    <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="cashflow_transactions">Список ДДС</div>
                    </div>
                    <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="make_cashflow_transactions"> Создать запись ДДС</div>
                    </div>
                    <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="make-pay">Создать оплату клиента</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                            Клиенты
                        </button>
                    </h2>
                    <div id="flush-collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="all-customers">Cписок пользователей</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseThree">
                            Товар
                        </button>
                    </h2>
                    <div id="flush-collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="goods_in_office">Товар в наличии в офисе</div>
                    </div>
                    <div id="flush-collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="add_new_good_in_office_card">Добавить новый товар в офис</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFive" aria-expanded="false" aria-controls="flush-collapseThree">
                            Поставщики
                        </button>
                    </h2>
                    <div id="flush-collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="supplier_settlements">Статистика по поставщикам</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseSix" aria-expanded="false" aria-controls="flush-collapseThree">
                            Загрузка прайсов
                        </button>
                    </h2>
                    <div id="flush-collapseSix" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body menu-item-container" target="excel_upload">Загрузить файл</div>
                    </div>
                </div>
            </div>
        </div>
        <div id="content">
            <div id="dashboard" class="container admin-content-item">
                <div class="container py-4">
                                        {{-- Заголовок --}}
                                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                                            <div>
                                                <h1 class="h3 mb-1">Финансовый дашборд</h1>
                                                <div class="text-muted">Общий контроль денег, возвратов и обязательств</div>
                                            </div>

                                            <form method="GET" action="{{ url('/admin') }}" class="row g-2 mt-3 mt-md-0">
                                                <div class="col-auto">
                                                    <input type="date" name="date_from" class="form-control"
                                                        value="{{ request('date_from', now()->startOfMonth()->format('Y-m-d')) }}">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="date" name="date_to" class="form-control"
                                                        value="{{ request('date_to', now()->format('Y-m-d')) }}">
                                                </div>
                                                <div class="col-auto">
                                                    <button class="btn btn-dark">Применить</button>
                                                </div>
                                            </form>
                                        </div>

                                        {{-- KPI карточки --}}
                                        <div class="row g-3 mb-4">
                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body">
                                                        <div class="text-muted small mb-1">Остаток денег</div>
                                                        <div class="fs-3 fw-bold">
                                                            {{ number_format($financeDashboard['financeKpi']['balance'] ?? 0, 0, '.', ' ') }} ₸
                                                        </div>
                                                        <div class="small text-muted mt-2">По всем счетам на текущий момент</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body">
                                                        <div class="text-muted small mb-1">Приход за период</div>
                                                        <div class="fs-3 fw-bold text-success">
                                                            {{ number_format($financeDashboard['financeKpi']['income'] ?? 0, 0, '.', ' ') }} ₸
                                                        </div>
                                                        <div class="small text-muted mt-2">Все входящие движения денег</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body">
                                                        <div class="text-muted small mb-1">Расход за период</div>
                                                        <div class="fs-3 fw-bold text-danger">
                                                            {{ number_format($financeDashboard['financeKpi']['expense'] ?? 0, 0, '.', ' ') }} ₸
                                                        </div>
                                                        <div class="small text-muted mt-2">Все исходящие движения денег</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body">
                                                        <div class="text-muted small mb-1">Чистый поток</div>
                                                        @php $netFlow = $financeDashboard['financeKpi']['net_flow'] ?? 0; @endphp
                                                        <div class="fs-3 fw-bold {{ $netFlow >= 0 ? 'text-success' : 'text-danger' }}">
                                                            {{ number_format($netFlow, 0, '.', ' ') }} ₸
                                                        </div>
                                                        <div class="small text-muted mt-2">Приход минус расход</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-4">
                                            {{-- Остатки по счетам --}}
                                            <div class="col-12 col-xl-5">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-header bg-white border-0 pb-0">
                                                        <h2 class="h5 mb-1">Остатки по счетам</h2>
                                                        <div class="text-muted small">Где именно находятся деньги</div>
                                                    </div>
                                                    <div class="card-body">
                                                        @forelse($financeDashboard['financeAccountsSummary'] ?? [] as $account)
                                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                <div>
                                                                    <div class="fw-semibold">{{ $account['name'] }}</div>
                                                                    <div class="small text-muted">
                                                                        Приход: {{ number_format($account['income'] ?? 0, 0, '.', ' ') }} ₸
                                                                        / Расход: {{ number_format($account['expense'] ?? 0, 0, '.', ' ') }} ₸
                                                                    </div>
                                                                </div>
                                                                <div class="fw-bold {{ ($account['balance'] ?? 0) >= 0 ? 'text-dark' : 'text-danger' }}">
                                                                    {{ number_format($account['balance'] ?? 0, 0, '.', ' ') }} ₸
                                                                </div>
                                                            </div>
                                                        @empty
                                                            <div class="text-muted">Нет данных по счетам</div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Расходы по категориям --}}
                                            <div class="col-12 col-xl-7">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-header bg-white border-0 pb-0">
                                                        <h2 class="h5 mb-1">Расходы по категориям</h2>
                                                        <div class="text-muted small">На что уходят деньги</div>
                                                    </div>
                                                    <div class="card-body">
                                                        @forelse($financeDashboard['financeExpenseBreakdown'] ?? [] as $item)
                                                            @php
                                                                $expenseTotal = $financeDashboard['financeKpi']['expense'] ?? 0;
                                                                $percent = $expenseTotal > 0
                                                                    ? round((($item['amount'] ?? 0) / $expenseTotal) * 100, 1)
                                                                    : 0;
                                                            @endphp

                                                            <div class="mb-3">
                                                                <div class="d-flex justify-content-between mb-1">
                                                                    <div class="fw-semibold">{{ $item['name'] }}</div>
                                                                    <div>{{ number_format($item['amount'] ?? 0, 0, '.', ' ') }} ₸</div>
                                                                </div>
                                                                <div class="progress" style="height: 8px;">
                                                                    <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%"></div>
                                                                </div>
                                                                <div class="small text-muted mt-1">{{ $percent }}%</div>
                                                            </div>
                                                        @empty
                                                            <div class="text-muted">Нет данных по расходам</div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>

                                        {{-- Кредиторка --}}
<div class="row g-3 mb-4">

    {{-- Кредиторка всего --}}
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Кредиторка всего</div>
                <div class="fs-3 fw-bold text-dark">
                    {{ number_format($totalSupplierDebt ?? 0, 0, '.', ' ') }} ₸
                </div>
                <div class="small text-muted mt-2">Общая сумма долга поставщикам</div>
            </div>
        </div>
    </div>

    {{-- Переплата поставщикам --}}
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Переплата поставщикам</div>
                <div class="fs-3 fw-bold text-success">
                    {{ number_format($totalSupplierOverpayment ?? 0, 0, '.', ' ') }} ₸
                </div>
                <div class="small text-muted mt-2">Авансы и переплаты</div>
            </div>
        </div>
    </div>

    {{-- Просроченная кредиторка --}}
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Просроченная кредиторка</div>
                <div class="fs-3 fw-bold text-danger">
                    {{ number_format($overdueSupplierDebt ?? 0, 0, '.', ' ') }} ₸
                </div>
                <div class="small text-muted mt-2">Просроченная часть текущего долга</div>
            </div>
        </div>
    </div>

    {{-- Поставщиков с долгом --}}
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Поставщиков с долгом</div>
                <div class="fs-3 fw-bold text-primary">
                    {{ $suppliersWithDebtCount ?? 0 }}
                </div>
                <div class="small text-muted mt-2">Активные обязательства</div>
            </div>
        </div>
    </div>

</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pb-0">
        <h2 class="h5 mb-1">Кредиторка по поставщикам</h2>
        <p class="text-muted small mb-0">Текущая задолженность, переплаты и просроченная часть по поставщикам</p>
    </div>

    <div class="card-body p-0">
        @if(($supplierBalancesTable ?? collect())->count())
            <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                <table class="table align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Поставщик</th>
                            <th class="text-end">Баланс</th>
                            <th class="text-end">Просрочено</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierBalancesTable as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->supplier }}</td>

                                <td class="text-end fw-bold {{ $row->balance < 0 ? 'text-success' : 'text-dark' }}">
                                    @if($row->balance < 0)
                                        +{{ number_format(abs($row->balance), 0, '.', ' ') }} ₸
                                    @else
                                        {{ number_format($row->balance, 0, '.', ' ') }} ₸
                                    @endif
                                </td>

                                <td class="text-end fw-bold {{ ($row->overdue_balance ?? 0) > 0 ? 'text-danger' : 'text-muted' }}">
                                    {{ number_format($row->overdue_balance ?? 0, 0, '.', ' ') }} ₸
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-3 text-muted">Нет данных по взаиморасчетам с поставщиками.</div>
        @endif
    </div>
</div>
</div>

                                           
                            {{-- Второй ряд KPI --}}
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="text-muted small mb-1">Возвраты клиентам</div>
                                            <div class="fs-4 fw-bold text-danger">
                                                {{ number_format($financeDashboard['financeKpi']['customer_returns'] ?? 0, 0, '.', ' ') }} ₸
                                            </div>
                                            <div class="small text-muted mt-2">Сколько денег ушло клиентам за период</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="text-muted small mb-1">Возвраты от поставщиков</div>
                                            <div class="fs-4 fw-bold text-success">
                                                {{ number_format($financeDashboard['financeKpi']['supplier_refunds'] ?? 0, 0, '.', ' ') }} ₸
                                            </div>
                                            <div class="small text-muted mt-2">Сколько поставщики вернули за период</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                {{-- Остатки по счетам --}}
                                

                                {{-- Возвраты --}}
                                <div class="col-12 col-xl-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-white border-0 pb-0">
                                            <h2 class="h5 mb-1">Возвраты</h2>
                                            <div class="text-muted small">Контроль возвратов от клиентов и поставщиков</div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <div class="p-3 rounded bg-light">
                                                        <div class="small text-muted">Открытые возвраты</div>
                                                        <div class="fs-4 fw-bold">
                                                            {{ $financeDashboard['financeReturnsStats']['open_count'] ?? 0 }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="p-3 rounded bg-light">
                                                        <div class="small text-muted">Закрытые возвраты</div>
                                                        <div class="fs-4 fw-bold">
                                                            {{ $financeDashboard['financeReturnsStats']['closed_count'] ?? 0 }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="p-3 rounded bg-light">
                                                        <div class="small text-muted">Выплачено клиентам</div>
                                                        <div class="fs-5 fw-bold text-danger">
                                                            {{ number_format($financeDashboard['financeReturnsStats']['customer_paid'] ?? 0, 0, '.', ' ') }} ₸
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="p-3 rounded bg-light">
                                                        <div class="small text-muted">Получено от поставщиков</div>
                                                        <div class="fs-5 fw-bold text-success">
                                                            {{ number_format($financeDashboard['financeReturnsStats']['supplier_received'] ?? 0, 0, '.', ' ') }} ₸
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border-top pt-3">
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Потери на возвратах</span>
                                                    <span class="fw-bold text-danger">
                                                        {{ number_format($financeDashboard['financeReturnsStats']['loss'] ?? 0, 0, '.', ' ') }} ₸
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Последние операции --}}
                                <div class="col-12 col-xl-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-white border-0 pb-0">
                                            <h2 class="h5 mb-1">Последние операции</h2>
                                            <div class="text-muted small">Последние движения денег в системе</div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height:350px; overflow-y:auto;">
                                                <table class="table align-middle mb-0">
                                                    <thead class="table-light" style="position: sticky; top:0; z-index:1;">
                                                        <tr>
                                                            <th>Дата</th>
                                                            <th>Тип</th>
                                                            <th>Описание</th>
                                                            <th>Счет</th>
                                                            <th class="text-end">Сумма</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($financeDashboard['financeLatestTransactions'] ?? [] as $txn)
                                                            <tr>
                                                                <td class="small text-muted">
                                                                    {{ \Carbon\Carbon::parse($txn['txn_at'])->format('d.m.Y H:i') }}
                                                                </td>
                                                                <td>
                                                                    @if(($txn['direction'] ?? '') === 'in')
                                                                        <span class="badge text-bg-success">Приход</span>
                                                                    @else
                                                                        <span class="badge text-bg-danger">Расход</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div class="fw-semibold">{{ $txn['subcategory'] ?? '—' }}</div>
                                                                    <div class="small text-muted">{{ $txn['counterparty'] ?? '' }}</div>
                                                                </td>
                                                                <td>{{ $txn['account_name'] ?? '—' }}</td>
                                                                <td class="text-end fw-bold {{ ($txn['direction'] ?? '') === 'in' ? 'text-success' : 'text-danger' }}">
                                                                    {{ number_format($txn['amount'] ?? 0, 0, '.', ' ') }} ₸
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted py-4">Нет операций</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Подсказка/сводка --}}
                                <div class="col-12">
                                    <div class="alert alert-light border shadow-sm mb-0">
                                        <div class="fw-semibold mb-1">Что показывает этот экран</div>
                                        <div class="small text-muted">
                                            Сверху — ключевые суммы за выбранный период. Ниже — кредиторка и долги по поставщикам.
                                            Слева — где лежат деньги. Справа — основные категории расходов.
                                            Внизу — возвраты и последние движения денег.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>
            <div id="orders" class="container admin-content-item">
                <div id="orders-filter">
                    
                </div>
                <div id="admin-panel-orders-total-wrapper">
                    <div id="admin-panel-orders-by-channel-header">
                        <div>Показать статистику</div>
                        <img src="/images/plus-24.png" alt="open/close table" id="show-close-admin-panel-statistic-wrapper">
                    </div>
                    <div id="admin-panel-orders-by-channel" status="closed">
                        <table class="table table-striped">
                            <thead>
                                <th>Канал продаж</th>
                                <th>Сумма</th>
                                <th>С/С</th>
                                <th>Маржа грязная</th>
                                <th>Маржа грязная, %</th>
                                <th>Кол-во продаж</th>
                                <th>Средний чек</th>
                                <th>% от общих продаж</th>
                                <th>Комиссия</th>
                                <th>Маржа чистая</th>.
                                <th>Маржа чистая, %</th>
                            </thead>
                            @foreach ($sales_statistics as $sale_channel => $data)
                            <tr>
                                <td>{{ $sale_channel }}</td>
                                <td>{{ $data['totalSalesSum'] }}</td>
                                <td>{{ $data['totalSalesPrimeCostSum'] }}</td>
                                <td>{{ $data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] }}</td>
                                <td>{{ $data['totalSalesSum'] ? round(100 - (($data['totalSalesPrimeCostSum'] * 100) / $data['totalSalesSum']), 2) : 0 }}%</td>
                                <td>{{ $data['countOfSales'] }}</td>
                                <td>{{ $data['countOfSales'] ? round($data['totalSalesSum'] / $data['countOfSales']) : 0 }}</td>
                                <td>{{ $totalSalesSum ? round(($data['totalSalesSum'] * 100) /  $totalSalesSum, 2) : 0 }}</td>
                                <td>{{ round(($data['totalSalesSum'] * 3) /  100) }}</td>
                                <td>
                                    @if($sale_channel == 'kaspi')
                                    {{ ($data['totalSalesSum'] * 12) /  100 }}
                                    @endif
                                </td>
                                <td>
                                    @if($sale_channel == 'kaspi')
                                    {{ round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - (($data['totalSalesSum'] * 3) /  100) - ($data['totalSalesSum'] * 12) /  100) }}
                                    @else
                                    {{ round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - (($data['totalSalesSum'] * 3) /  100)) }}
                                    @endif
                                </td>
                                <td>
                                    @if($sale_channel == 'kaspi')
                                        @if($data['totalSalesSum'] > 0)
                                            {{ 100 - round(100 - ((round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - (($data['totalSalesSum'] * 3) /  100) - ($data['totalSalesSum'] * 12) /  100)* 100) / $data['totalSalesSum']), 2) }}%
                                        @else
                                            0
                                        @endif
                                    @else
                                        @if($data['totalSalesSum'] > 0)
                                            {{ 100 - round(100 - ((round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - (($data['totalSalesSum'] * 3) /  100))* 100) / $data['totalSalesSum']), 2) }}%
                                        @else
                                            0
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Общий оборот</strong>
                                </td>
                                <td>{{ number_format($totalSalesSum, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>С/С</strong>
                                </td>
                                <td>{{ number_format($totalPrimeCostSum, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Кол-во продаж</strong>
                                </td>
                                <td>{{ number_format($totalCountOfSales, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Средний чек</strong>
                                </td>
                                <td>{{ $totalCountOfSales ? number_format(round($totalSalesSum / $totalCountOfSales), 0, '.', ' ') : 0 }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Маржа грязная</strong>
                                </td>
                                <td>{{ number_format($totalSalesSum - $totalPrimeCostSum, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Маржа грязная, %</strong>
                                </td>
                                <td>{{ $totalSalesSum ? number_format(round(100 - (($totalPrimeCostSum * 100) / $totalSalesSum), 2), 2, '.', ' ') : 0 }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Комиссии</strong>
                                </td>
                                <td>{{ number_format($kaspiComission, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Маржа чистая</strong>
                                </td>
                                <td>{{ number_format($marginClear, 0, '.', ' ') }}</td>
                            </tr>
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>Маржа чистая, %</strong>
                                </td>
                                <td>{{ $totalSalesSum? round((($marginClear * 100) / $totalSalesSum), 2) : 0 }}</td>
                            </tr>
                            
                        </table>
                    </div>
                </div>
                <div id="stats_graphics">
                    <div id="stats_graphics_header">
                        <span>График</span>
                        <img src="/images/plus-24.png" alt="open/close table" id="show-close-admin-panel-graphics">
                    </div>
                    <div id="stats_graphics_content" status="closed">
                        <h2>1. Сумма продаж и закупа по месяцам</h2>
                        <canvas id="salesChart" width="800" height="400"></canvas>

                        <h2>2. Статистика продаж за весь период</h2>
                        <div id="admin-panel-orders-from begin" status="closed">
                            <table class="table table-striped">
                                <thead>
                                    <th>Канал продаж</th>
                                    <th>Сумма</th>
                                    <th>С/С</th>
                                    <th>Маржа грязная</th>
                                    <th>Маржа грязная, %</th>
                                    <th>Кол-во продаж</th>
                                    <th>Средний чек</th>
                                    <th>% от общих продаж</th>
                                    <th>Комиссия</th>
                                    <th>Маржа чистая</th>.
                                    <th>Маржа чистая, %</th>
                                </thead>
                                @foreach ($sales_statistics_from_begin as $sale_channel => $data)
                                <tr>
                                    <td>{{ $sale_channel }}</td>
                                    <td>{{ number_format($data['totalSalesSum'], 0, '', ' '); }}</td>
                                    <td>{{ number_format($data['totalSalesPrimeCostSum'], 0, '', ' ') }}</td>
                                    <td>{{ number_format($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'], 0, '', ' ') }}</td>
                                    <td>{{ number_format($data['totalSalesSum'] ? round(100 - (($data['totalSalesPrimeCostSum'] * 100) / $data['totalSalesSum']), 2) : 0, 0, '', ' ') }}%</td>
                                    <td>{{ number_format($data['countOfSales'], 0, '', ' ') }}</td>
                                    <td>{{ number_format($data['countOfSales'] ? round($data['totalSalesSum'] / $data['countOfSales']) : 0, 0, '', ' ') }}</td>
                                    <td>{{ $salesSumFromBegin ? round(($data['totalSalesSum'] * 100) /  $salesSumFromBegin, 2) : 0 }}</td>
                                    <td>
                                        @if($sale_channel == 'kaspi')
                                        {{ number_format(($data['totalSalesSum'] * 12) /  100, 0, '', ' ') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($sale_channel == 'kaspi')
                                        {{ number_format(round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - ($data['totalSalesSum'] * 12) /  100), 0, '', ' ') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($sale_channel == 'kaspi')
                                            @if($data['totalSalesSum'] > 0)
                                                {{ number_format(100 - round(100 - ((round($data['totalSalesSum'] - $data['totalSalesPrimeCostSum'] - (($data['totalSalesSum'] * 3) /  100) - ($data['totalSalesSum'] * 12) /  100)* 100) / $data['totalSalesSum']), 2), 0, '', ' ') }}%
                                            @else
                                                0
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Общий оборот</strong>
                                    </td>
                                    <td>{{ number_format($salesSumFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>С/С</strong>
                                    </td>
                                    <td>{{ number_format($primeCostSumFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Кол-во продаж</strong>
                                    </td>
                                    <td>{{ number_format($countOfSalesFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Кол-во проданных единиц</strong>
                                    </td>
                                    <td>{{ number_format($totalItemsSoldFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Средний чек</strong>
                                    </td>
                                    <td>{{ $salesSumFromBegin ? number_format(round($salesSumFromBegin / $countOfSalesFromBegin), 0, '.', ' ') : 0 }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Маржа грязная</strong>
                                    </td>
                                    <td>{{ number_format($salesSumFromBegin - $primeCostSumFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Маржа грязная, %</strong>
                                    </td>
                                    <td>{{ $salesSumFromBegin ? number_format(round(100 - (($primeCostSumFromBegin * 100) / $salesSumFromBegin), 2), 2, '.', ' ') : 0 }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Комиссии</strong>
                                    </td>
                                    <td>{{ number_format($kaspiComissionFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Маржа чистая</strong>
                                    </td>
                                    <td>{{ number_format($marginClearFromBegin, 0, '.', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td colspan="3">
                                        <strong>Маржа чистая, %</strong>
                                    </td>
                                    <td>{{ $salesSumFromBegin? round((($marginClearFromBegin * 100) / $salesSumFromBegin), 2) : 0 }}</td>
                                </tr>
                            </table>
                        </div>

                        <h2>3. График по дням за текущий месяц</h2>
                        <div class="chart-container" style="position: relative; width: 100%; max-width: 1000px; margin: 20px auto;">
                            <canvas id="reportMonthChart" height="150"></canvas>
                        </div>

                        <div id="salesSummary" style="text-align:center; font-size: 1.1em; margin-top: 20px;"></div>


                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.1.0"></script>
                        <script>
                            const stats = {!! json_encode($stats) !!};

                            const labels = Object.keys(stats);
                            const salesData = labels.map(label => stats[label].total_sales_sum);
                            const purchaseData = labels.map(label => stats[label].total_purchase_sum);

                            // 1. График "Сумма продаж и закупа по месяцам"
                            new Chart(document.getElementById('salesChart'), {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        {
                                            label: 'Продажи (с наценкой)',
                                            data: salesData,
                                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                                            borderColor: 'rgba(75, 192, 192, 1)',
                                            fill: true,
                                            tension: 0.3
                                        },
                                        {
                                            label: 'Закуп (себестоимость)',
                                            data: purchaseData,
                                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                                            borderColor: 'rgba(255, 99, 132, 1)',
                                            fill: true,
                                            tension: 0.3
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: 'Сумма продаж и закупа по месяцам'
                                        }
                                    }
                                }
                            });

                            // 3. График "Сумма продаж и закупа за отчетный период (по дням)"
                            const reportLabels = @json($labels);
                            const reportSalesData = @json($salesData);
                            const reportPurchaseData = @json($purchaseData);
                            const pointColors = @json($pointColors);
                            const actualSum = {{ $actualSum }};
                            const plannedSum = {{ $plannedSum }};

                           
                    document.addEventListener("DOMContentLoaded", function () {
                        const ctx = document.getElementById('reportMonthChart');

                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: reportLabels,
                                datasets: [
                                    {
                                        label: 'Продажи (с наценкой)',
                                        data: reportSalesData,
                                        borderColor: 'rgba(0, 123, 255, 0.6)',
                                        backgroundColor: 'rgba(0, 0, 0, 0)',
                                        fill: false,
                                        tension: 0.3,
                                        pointBackgroundColor: pointColors,
                                        pointRadius: 6,
                                        pointHoverRadius: 7
                                    },
                                    {
                                        label: 'Закуп (себестоимость)',
                                        data: reportPurchaseData,
                                        backgroundColor: 'rgba(255, 99, 132, 0.3)',
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        fill: true,
                                        tension: 0.3
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Сумма продаж и закупа за отчетный месяц (по дням)'
                                    },
                                    annotation: {
                                        annotations: {
                                            planLine: {
                                                type: 'line',
                                                yMin: 300000,
                                                yMax: 300000,
                                                borderColor: 'rgba(255, 159, 64, 1)',
                                                borderWidth: 2,
                                                label: {
                                                    content: 'План: 300 000 ₸',
                                                    enabled: true,
                                                    position: 'start',
                                                    backgroundColor: 'rgba(255, 159, 64, 0.7)'
                                                }
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'День'
                                        }
                                    },
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Сумма (₸)'
                                        },
                                        ticks: {
                                            callback: function (value) {
                                                return value.toLocaleString() + ' ₸';
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        // Вывод итогов под графиком
                        const summaryEl = document.getElementById('salesSummary');
                        const summaryColor = actualSum >= plannedSum ? 'green' : 'red';
                        summaryEl.innerHTML = `
                            Плановая сумма продаж: <b>${plannedSum.toLocaleString()} ₸</b><br>
                            Фактическая сумма продаж: <b style="color:${summaryColor}">${actualSum.toLocaleString()} ₸</b>
                        `;
                    });


                        </script>
                    </div>
                </div>
                

                @foreach ($orders as $orderItem)
                <div class="admin-order-item-wrapper">
                    <div class="order-item-header">
                       <div class="order-item-id">
                            {{ $orderItem->id }}
                       </div>
                       <div class="order-item-user-name">
                            <span>{{ $orderItem->user->name }}</span> 
                            <span style="font-size: 0.7em">{{ $orderItem->customer_phone }}</span> 
                       </div>
                       <div class="order-item-status">
                            {{ $orderItem->status }} <img src="/images/clock-wait-16.png">
                       </div>
                       <div class="order-item-date">
                            {{ $orderItem->date->format('d.m.y') }}
                       </div>
                       <div class="order-item-time">
                            {{ $orderItem->sale_channel }}
                       </div>
                       
                       <div class="admin-order-item-sum">
                            <span style="font-weight: 600;color:green">{{ number_format($orderItem->sum_with_margine, 2, ',', ' ') }}</span>
                            <span style="font-style: italic;color:red;font-size: 0.7em">
                                {{ number_format($orderItem->sum, 2, ',', ' ') }}
                                @if ($orderItem->sum_with_margine != 0)
                                %{{ number_format(($orderItem->sum_with_margine - $orderItem->sum) * 100 / $orderItem->sum_with_margine, 2, ',', ' ') }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="order-item-products-wrapper">
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
                                {{ number_format($product->priceWithMargine, 0, ',', ' ') }}
                            </div>
                            <div class="order-products-item_sum">
                                {{ number_format($product->itemSumWithMargine, 0, ',', ' ') }}
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
            <div id="make_customer_return" class="container admin-content-item">
                <form method="POST" action="/makeCustomerReturn" id="customer-return-form">
                @csrf

                <div class="container py-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h4 class="mb-0">Создание возврата клиента</h4>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-3">
                                    <label class="form-label">Заказ</label>
                                    <input type="hidden" name="customer_id" id="customer_id" value="">
                                    <select name="order_id" class="form-control" id="cr_order_id">
                                        <option selected disabled>выбери заказ</option>
                                        @foreach ($orders as $order)
                                            <option value="{{ $order->id }}" 
                                            data-customer-id="{{ $order->id }}"
                                            data-customer-data="{{ $order?->customer?->name ?? $order?->customer?->phone ?? 'нет данных' }}"
                                            data-customer-phone="{{ $order->customer_phone }}"
                                            >
                                            #{{ $order->id }}, 
                                            от {{ \Carbon\Carbon::parse($order->date)->translatedFormat('d F') }},  
                                            {{ $order->sum_with_margine }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Позиции заказа</label>
                                    
                                    <select name="order_product_id" class="form-control" id="cr_order_products">
                                        <option selected disabled>выбери позицию</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Данные клиента</label>
                                    <input type="text" name="customer_id" id="customer_data" class="form-control" value="{{ old('customer_id') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Данные поставщика</label>
                                    <input type="text" name="supplier_name" class="form-control" id="cr_supplier_name">
                                    <input type="hidden" name="supplier_id" class="form-control" id="cr_supplier_id">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Учетная запись</label>
                                    <input type="hidden" name="user_id" class="form-control" value="{{ auth()->user()->id }}">
                                    <input type="text" name="user_name" class="form-control" value="{{ auth()->user()->name }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Телефон клиента</label>
                                    <input type="text" name="customer_phone" id="customer_phone" class="form-control" value="{{ old('customer_phone') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Дата возврата</label>
                                    <input type="date" name="return_date" class="form-control" value="{{ old('return_date', now()->format('Y-m-d')) }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Количество</label>
                                    <input type="hidden" id="control_cr_qty" value=''>
                                    <input type="number" step="1" min="1" name="qty" class="form-control qty-input" id="cr_qty">
                                    <div class="invalid-feedback" id="cr_qty_error"></div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Данные продажи и возврата клиенту</h5>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Цена продажи</label>
                                    <input type="number" name="sale_price" class="form-control sale-price-input" id="cr_product_price">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Сумма возврата клиенту</label>
                                    <input type="number" step="0.01" min="0" name="customer_refund_amount" id="customer_refund_amount" class="form-control customer-refund-amount-input" value="{{ old('customer_refund_amount', 0) }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Фактически выплачено клиенту</label>
                                    <input type="number" step="0.01" min="0" name="customer_refund_paid" class="form-control" value="0" id="customer_refund_paid">
                                    <div class="invalid-feedback" id="cr_customer_refund_paid_error"></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Возврат со счета</label>
                                    
                                     <select name="account_id_out" class="form-control" id="account_id_out">
                                        <option selected disabled>выбери счет</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Дата выплаты клиенту</label>
                                    <input type="date" name="customer_refund_date" class="form-control" value="{{ old('customer_refund_date') }}">
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Данные по поставщику</h5>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Закупочная цена</label>
                                    <input type="number" step="0.01" min="0" name="supplier_purchase_price"  id="supplier_purchase_price" class="form-control supplier-purchase-price-input" value="0">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Ожидаемая сумма от поставщика</label>
                                    <input type="number" step="0.01" min="0" name="supplier_refund_amount" class="form-control supplier-refund-amount-input" id="supplier_refund_amount" value="0">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Фактически получено от поставщика</label>
                                    <input type="number" step="0.01" min="0" name="supplier_refund_received" class="form-control" value="{{ old('supplier_refund_received', 0) }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Дата поступления от поставщика</label>
                                    <input type="date" name="supplier_refund_date" class="form-control" value="{{ old('supplier_refund_date') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Статус компенсации от поставщика</label>
                                    <select name="supplier_refund_status" class="form-select">
                                        <option value="pending" {{ old('supplier_refund_status') == 'pending' ? 'selected' : '' }}>Ожидается</option>
                                        <option value="received" {{ old('supplier_refund_status') == 'received' ? 'selected' : '' }}>Получено</option>
                                        <option value="not_expected" {{ old('supplier_refund_status') == 'not_expected' ? 'selected' : '' }}>Не ожидается</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Возврат на счет</label>
                                    
                                     <select name="account_id_in" class="form-control" id="account_id_in">
                                        <option selected disabled>выбери счет</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Дополнительно</h5>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Общий статус возврата</label>
                                    <select name="status" class="form-select">
                                        <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>В работе</option>
                                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Завершен</option>
                                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Отменен</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Дата закрытия</label>
                                    <input type="datetime-local" name="closed_at" class="form-control"
                                        value="{{ old('closed_at') ? \Carbon\Carbon::parse(old('closed_at'))->format('Y-m-d\TH:i') : '' }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Причина возврата</label>
                                    <input type="text" name="reason" class="form-control" value="{{ old('reason') }}">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Комментарий</label>
                                    <textarea name="comment" rows="4" class="form-control">{{ old('comment') }}</textarea>
                                </div>
                            </div>

                            
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Сохранить возврат</button>
                        </div>
                    </div>
                </div>
                </form>
            </div>
            <div id="customer_returns_list" class="container admin-content-item">
                <div id="cutomer-returns-wrapper">
                    <div id="customer-returns-header">
                        <h3>Возвраты от клиентов</h3>
                    </div>
                    <div id="customer-returns-content">
                        <table class="table table-success table-striped">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Заказ</th>
                                <th>Позиции заказа</th>
                                <th>Телефон клиента</th>
                                <th>Данные поставщика</th>
                                <th>Дата возврата</th>
                                <th>Количество</th>
                                <th>Сумма возврата</th>
                                <th>Фактически выплачено </th>
                                <th>Возврат со счета</th>
                                <th>Получено от поставщика</th>
                                <th>Статус поставщика</th>
                                <th>Причина возврата</th>
                                <th>Общий статус возврата</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer_returns as $customer_return)
                            <tr class="">
                                <td class="customer-return-item-entity">#{{ $customer_return->id }}</td>
                                <td class="customer-return-item-entity">#{{ $customer_return->order_id }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->orderProduct->name }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->customer_phone }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->supplier_name }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->return_date }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->qty }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->customer_refund_amount }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->customer_refund_paid }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->customerCashflowTransaction?->account?->name ?? 'нет данных' }}</td>
                                <td class="customer-return-item-entity">{{ $customer_return->supplier_refund_received }}</td>
                                <td class="customer-return-item-entity">
                                    @if($customer_return->supplier_refund_status == 'pending')
                                        <span class="badge bg-warning text-dark">Ожидает</span>
                                    @elseif($customer_return->supplier_refund_status == 'received')
                                        <span class="badge bg-success">получен</span>
                                    @elseif($customer_return->supplier_refund_status == 'canceled')
                                        <span class="badge bg-danger">Отменен</span>
                                    @endif
                                </td>
                                <td class="customer-return-item-entity">{{ $customer_return->reason }}</td>
                                <td class="customer-return-item-entity">
                                    @if($customer_return->status == 'pending')
                                        <span class="badge bg-warning text-dark">Ожидает</span>
                                    @elseif($customer_return->status == 'completed')
                                        <span class="badge bg-success">Завершен</span>
                                    @elseif($customer_return->status == 'canceled')
                                        <span class="badge bg-danger">Отменен</span>
                                    @endif
                                </td>
                                <td class="customer-return-item-entity">
                                    @if ($customer_return->status != 'completed')
                                        <a href="{{ route('supplierRefundComplete', $customer_return) }}" 
                                        class="btn btn-sm btn-primary">
                                        Изменить
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
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
                <div id="supplier_settlements_wrapper">
                    @foreach ($suppliers_settlements as $supplier => $settlementsByMonth)
                        <div class="suppliers_settlements_item" style="background-color:{{ $settlementsByMonth['color'] }}">
                            <div class="supplier_name">{{ $supplier }}</div>
                            @foreach ($settlementsByMonth as $month => $sum)
                                @if($month == 'color' || $month == 'type')
                                    @continue
                                @else
                                <div class="settlementsByMonth">
                                    <div class="supplier_settlements_months">
                                        <div class="month">{{ $month }}</div>
                                    </div>
                                    <div class="supplier_settlements_sums">
                                        <div class="sum">{{ number_format($sum, 0, '', ' ') }}</div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach

                </div>
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
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="submit" class="btn btn-success" value="Провести оплату">
                </form>
            </div>
            <div id="manually-order" class="container admin-content-item">
                <div class="alert" style="align-text:center;" id="alert-admin">
                    <div style="display:flex;justify-content:flex-end;" class="close-flash"></div>
                </div>
                <div id="manually-order-wrapper">
                    @csrf
                    <div id="manually-order-main">
                        <input type="hidden" name="user_id" value="{{ Auth()->user()->id }}" class="manually-order-main-info">
                        <label for="basic-url" class="form-label">Дата</label>
                        <div class="input-group mb-2 manually-order-main">
                            <input type="date" class="form-control manually-order-main-info" name="date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <label for="basic-url" class="form-label">Имя клиента</label>
                        <div class="input-group mb-2 manually-order-main">
                            <input type="text" class="form-control manually-order-main-info" name="customer_name" placeholder="имя" required>
                        </div>
                        <label for="basic-url" class="form-label">Телефон клиента</label>
                        <div class="input-group mb-2 manually-order-main">
                            <input type="telephone" class="form-control manually-order-main-info" name="customer_phone" required>
                        </div>
                        <label for="basic-url" class="form-label">Канал продаж</label>
                        <div class="input-group mb-2 manually-order-main">
                            <select name="sale_channel" class="form-control manually-order-main-info" required id="manualy_order_sale_channel">
                                <option selected disabled>выбери канал продаж</option>
                                <option value="2gis">2gis</option>
                                <option value="olx">Olx</option>
                                <option value="site">Сайт</option>
                                <option value="friends">Свои</option>
                                <option value="kaspi">Каспи</option>
                                <option value="repeat_request">Повторное обращение</option>
                            </select>
                        </div>
                        <label for="basic-url" class="form-label" id="manually-order-list-open">Товар</label>
                    </div>

                    <!-- NAV TABS -->
                    <ul class="nav nav-tabs" id="customTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button
                        class="nav-link active"
                        id="tab1-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#tab1"
                        type="button"
                        role="tab"
                        aria-controls="tab1"
                        aria-selected="true"
                        >
                        Информация о товарах
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button
                        class="nav-link"
                        id="tab2-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#tab2"
                        type="button"
                        role="tab"
                        aria-controls="tab2"
                        aria-selected="false"
                        >
                        Детали оплаты
                        </button>
                    </li>
                    </ul>

                    <!-- TAB CONTENT -->
                    <div class="tab-content border border-top-0 p-3">
                    <div
                        class="tab-pane fade show active"
                        id="tab1"
                        role="tabpanel"
                        aria-labelledby="tab1-tab"
                    >
                        <div id="manually-order-parts-list">
                                <div id="manually-order-bar">
                                    <a href="###" id="add_parts_list_item">Добавить еще товар</a>
                                    <input type="submit" value="Оформить заказ" class="btn btn-sm btn-success" id="manually-order-submit">
                                </div>
                                <div class="manually-order-parts-list-item">
                                    <div class="manually-order-parts-list-item-header">
                                        <label class="form-label parts-list-item">Артикул</label>
                                        <label class="form-label">Бренд</label>
                                        <label class="form-label">Наименование</label>
                                        <label class="form-label">Кол-во</label>
                                        <label class="form-label">С/С</label>
                                        <label class="form-label">Розница</label>
                                        <label class="form-label">Поставщик</label>
                                        <label class="form-label">Доставка</label>
                                    </div>
                                    <div class="manually-order-parts-list-item-content">
                                        <input type="text" class="form-control" name="article" required>
                                        <input type="text" class="form-control" name="brand" required>
                                        <input type="text" class="form-control" name="name" required>
                                        <input type="number" class="form-control manually-order-parts-list-item-qty" name="qty" required> 
                                        <input type="number" class="form-control manually-order-parts-list-price" name="price" required>
                                        <input type="number" class="form-control manually-order-parts-list-price-with-margine" name="priceWithMargine" required>
                                        <select name="from_stock" class="order_product_item_supplier">
                                            <option disabled selected>Выбери поставщика</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" class="form-control" name="deliveryTime" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                            </div>
                            <div id="manually-order-total">
                                <div id="manualy-order-total-sum-with-margine" class="manualy-order-total-item">
                                    Итого розница: <span id="manualy-order-total-sum-with-margine-num" class="manualy-order-total-item-num">0</span>
                                </div>
                                <div id="manualy-order-total-prime-cost-sum" class="manualy-order-total-item">
                                    Итого С/С: <span id="manualy-order-total-prime-cost-sum-inner" class="manualy-order-total-item-num">0</span>
                                </div>
                                <div id="manualy-order-total-qty" class="manualy-order-total-item">
                                    Итого кол-во: <span id="manualy-order-total-qty-inner" class="manualy-order-total-item-num">0</span>
                                </div>
                            </div>
                    </div>

                    <div
                        class="tab-pane fade"
                        id="tab2"
                        role="tabpanel"
                        aria-labelledby="tab2-tab"
                    >
                        <div id="manualy-order-payment-details">
                            <div id="manualy-order-payment-details-header">
                                <label class="form-label">Счет для поступления</label>
                                <label class="form-label">Дата</label>
                                <label class="form-label">Сумма</label>
                                <label class="form-label">Тип транзаакции</label>
                                <label class="form-label">Комментарии</label>
                            </div>
                            <div id="manualy-order-payment-details-body">
                                <select name="account" id="" class="form-select">
                                    <option disabled selected>Выбери счет</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                                    @endforeach
                                </select>
                                <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" required>
                                <input type="number" class="form-control" name="amount" min="0" id="manualy-order-payment-details-amount" required>
                                <select name="type" id="" class="form-control">
                                    <option value="payment">Оплата</option>
                                    <option value="refund">Возврат</option>
                                </select>
                                <input type="text" class="form-control" name="comments" placeholder="комментарии">
                            </div>
                        </div>
                    </div>

                    </div>
                </div>
            </div>
            <div id="excel_upload" class="container admin-content-item">
                <form action="{{ url('import') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта от Адиля</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

                <form action="{{ url('import-in-office') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта товара в офисе</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

                <form action="{{ url('import-xui-poimi') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта хуй пойми склад</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

                <form action="{{ url('import-ingvar') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта Форсунки Ингвар</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>
                <form action="{{ url('import-voltage') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта VoltageKZ</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>
                <form action="{{ url('import-blue-star') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта BlueStar</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

                 <form action="{{ url('import-interkom') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта Интерком</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

                <form action="{{ url('import-adil-phaeton') }}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group{{ $errors->has('file') ? 'has-error' : '' }}">
                        <label for="file" class="control-label">Файл для импорта Адиль Фаэтон</label>

                        <input id="file" type="file" class="form-controll" name="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>

                        @if ($errors->has('file'))
                            <span class="help-block">
                                <strong>{{ $errors->first('file') }}</stromg>
                            </span>
                        @endif
                    </div>

                    <p>
                        <button type="submit" class="btn btn-success" name="submit">
                            <i class="fa fa-check"></i>Загрузить
                        </button>
                    </p>
                </form>

            </div>
            <div id="goods_in_office" class="container admin-content-item">
                <div class="alert" style="align-text:center;" id="alert-admin-goods-in-office">
                    <div style="display:flex;justify-content:flex-end;" class="close-flash"></div>
                </div>
                <div id="add_new_good_in_office_form_header">
                    Товаров в офисе: {{ $goods_in_office_count }} на сумму: {{ $goods_in_office_sum }}
                </div>
                <div id="goods_in_office_add_table_wrapper">
                    <table class="table table-hover">
                        <thead>
                            <th>OEM</th>
                            <th>Артикул</th>
                            <th>Бренд</th>
                            <th>Наименование</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th></th>
                            <th></th>
                        </thead>
                        <tbody>
                            @foreach ($goods_in_office as $good)
                                <tr>
                                    <input type="hidden" value="{{ $good['id'] }}" name="good_id">
                                    <td style="max-width:25%;">{{ mb_strimwidth($good['oem'], 0, 30, '...') }}</td>
                                    <td>{{ $good['article'] }}</td>
                                    <td>{{ $good['brand'] }}</td>
                                    <td>{{ $good['name'] }}</td>
                                    <td class="col-md-12"><input type="number" value="{{ $good['price'] }}" class="good_in_office_price form-control"></td>
                                    <td><input type="number" value="{{ $good['qty'] }}" class="good_in_office_qty form-control" min="1" style="width: 50px !important"></td>
                                    <td><button class="btn btn-sm btn-primary"class="good_in_office_change">Изменить</button></td>
                                    <td><img src="/images/dump-red-24.png" class="good_in_office_delete"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="add_new_good_in_office_card" class="container admin-content-item">
                <div class="alert" style="align-text:center;" id="alert-admin">
                    <div style="display:flex;justify-content:flex-end;" class="close-flash"></div>
                </div>
                <div id="add_new_good_in_office_form_wrapper">
                    <form action="/add_new_good_in_office" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">OEM номера, через ";"</label>
                            <textarea class="form-control" rows="3" name="oem"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Артикул</label>
                            <input type="text" class="form-control" require name="article">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Бренд</label>
                            <input type="text" class="form-control" require name="brand">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Наименование</label>
                            <input type="text" class="form-control"require name="name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Цена</label>
                            <input type="number" class="form-control"require min="100" step="1" name="price">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Кол-во</label>
                            <input type="number" class="form-control"require min="1" step="1" name="qty">
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
            <div id="cashflow_transactions" class="container admin-content-item">
                <div id="cashflow_transactions-wrapper">
                    <table class="table table-success table-striped">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип операции</th>
                                <th>Категория операции</th>
                                <th>Подкатегория</th>
                                <th>Счет</th>
                                <th>Контрагент</th>
                                <th>Поставщик</th>
                                <th>Сумма</th>
                                <th>Коментарий</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cashflow_transactions as $cft)
                            <tr class="">
                                <td class="cashflow-transactions-item-entity">{{ Carbon::parse($cft['txn_at'])->translatedFormat('j F Y') }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->direction == 'out' ? 'Расход' : 'Приход' }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->cashflowCategory->rus_name }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->subcategory }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->account->name }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->counterparty ?? '' }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->supplier->name ?? '' }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->amount }}</td>
                                <td class="cashflow-transactions-item-entity">{{ $cft->comment }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="make_cashflow_transactions" class="container admin-content-item">
                <form action="{{ route('make-cashflow-transaction')}}" method="post" id="make-cashflow-transaction-form">
                    @csrf
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Дата</label>
                        <input type="date" value="{{ date('Y-m-d') }}" name="txn_at" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Тип операции</label>
                         <select class="cft-direction form-select" name="direction" required>
                            <option selected disabled>Выбери тип операции</option>
                            <option value="in">Поступление</option>
                            <option value="out">Расход</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Категория операции</label>
                        <select class="cashflow-categories form-select" name="cashflow_categories_id" required>
                            <option selected disabled value="initial">Выбери категорию</option>
                            @foreach ($cashflow_categories as $cf_category)
                                <option value="{{ $cf_category['id'] }}"  
                                data-direction="{{ $cf_category['default_direction'] }}">
                                    {{ $cf_category['rus_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Категория расхода</label>
                        <select class="expense-categories form-select" name="expense_categories_id">
                            <option selected disabled>Выбери тип расхода</option>
                            @foreach ($expense_categories as $ex_category)
                                <option value="{{ $ex_category['id'] }}">{{ $ex_category['rus_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Подкатегория записи</label>
                        <input type="text" name="subcategory" class="subcategory form-control" placeholder="подкатегория (краткое описание: например бензин)">
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Счет</label>
                        <select class="accounts form-select" name="account_id" required>
                            <option selected disabled>Выбери счет</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Контрагент</label>
                        <input type="text" name="counterparty" class="counterparty form-control" placeholder="введи контрагента">
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Поставщик</label>
                        <select class="suppliers form-select" name="supplier_id">
                            <option disabled selected>Выбери поставщика</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Сумма</label>
                        <input class="cft-amount form-control" type="number" min="0" step="1" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="cft-header-item form-label">Коментарий</label>
                        <input type="text" class="cft-coment form-control" name="comment">
                        <input type="hidden" name="user_id" value="{{ auth()->id() }}"> 
                    </div>
                    <button type="submit" class="btn btn-primary">Записать</button>
                </form>
            </div>
        </div>
    </div>
@endsection