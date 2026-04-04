@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Возврат #{{ $customerReturn->id }}</h3>

        <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">
            ← Назад
        </a>
    </div>

    <form action="{{ route('customer_returns.update', $customerReturn->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header">
                Общая информация
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label text-muted">Заказ</label>
                        <div class="form-control bg-light">#{{ $customerReturn->order_id }}</div>
                        <input type="hidden" value="{{ $customerReturn->order_id }}" name="order_id">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Позиция заказа</label>
                        <div class="form-control bg-light">{{ $customerReturn->order_product_id }}</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Поставщик</label>
                        <input 
                            type="text"
                            name="supplier_name"
                            class="form-control"
                            value="{{ $customerReturn->supplier_name }}"
                            readonly
                        >
                        <input type="hidden" value="{{ $customerReturn->supplier_id }}" name="supplier_id">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Администратор</label>
                        <div class="form-control bg-light">{{ $customerReturn->user?->name ?? '—' }}</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Дата возврата</label>
                        <div class="form-control bg-light">{{ $customerReturn->return_date }}</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Количество</label>
                        <div class="form-control bg-light">{{ $customerReturn->qty }}</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Цена продажи</label>
                        <div class="form-control bg-light">{{ number_format($customerReturn->sale_price, 0, '', ' ') }} ₸</div>
                    </div>

                </div>
            </div>
        </div>


        <div class="card mb-4">
            <div class="card-header">
                Информация о клиенте
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label text-muted">Телефон клиента</label>
                        <div class="form-control bg-light">{{ $customerReturn->customer_phone }}</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Сумма возврата клиенту</label>
                        <div class="form-control bg-light">
                            {{ number_format($customerReturn->customer_refund_amount, 0, '', ' ') }} ₸
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Фактически выплачено</label>
                        <div class="form-control bg-light">
                            {{ number_format($customerReturn->customer_refund_paid, 0, '', ' ') }} ₸
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Дата выплаты</label>
                        <div class="form-control bg-light">
                            {{ $customerReturn->customer_refund_date ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Счет списания</label>
                        <div class="form-control bg-light">
                            {{ $customerReturn->customerCashflowTransaction?->account?->name ?? '—' }}
                        </div>
                    </div>

                </div>

            </div>
        </div>


        <div class="card mb-4">
            <div class="card-header">
                Компенсация от поставщика
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-4">
                        <label for="supplier_refund_amount" class="form-label">Ожидаемая компенсация</label>
                        <input
                            type="number"
                            step="1"
                            min="0"
                            name="supplier_refund_amount"
                            id="supplier_refund_amount"
                            class="form-control"
                            value="{{ old('supplier_refund_amount', $customerReturn->supplier_refund_amount) }}"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="supplier_refund_received" class="form-label">Фактически получено</label>
                        <input
                            type="number"
                            step="1"
                            min="0"
                            name="supplier_refund_received"
                            id="supplier_refund_received"
                            class="form-control"
                            value="{{ old('supplier_refund_received', $customerReturn->supplier_refund_received) }}"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="supplier_refund_date" class="form-label">Дата поступления</label>
                        <input
                            type="date"
                            name="supplier_refund_date"
                            id="supplier_refund_date"
                            class="form-control"
                            value="{{ old('supplier_refund_date', $customerReturn->supplier_refund_date) }}"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="supplier_account_id" class="form-label">Счет поступления</label>
                        <select name="account_id_in" id="supplier_account_id" class="form-select">
                            <option value="">Выберите счет</option>
                            @foreach($accounts as $account)
                                <option
                                    value="{{ $account->id }}"
                                    {{ old('supplier_account_id', $customerReturn->supplierCashflowTransaction?->account?->id) == $account->id ? 'selected' : '' }}
                                >
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="supplier_refund_status" class="form-label">Статус компенсации</label>
                        <select name="supplier_refund_status" id="supplier_refund_status" class="form-select">
                            <option value="pending">в ожидании</option>
                            <option value="received">получена</option>
                            <option value="not_expected">не ожидается</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                    <label class="form-label text-muted">Причина</label>
                    <div class="form-control bg-light">
                        {{ $customerReturn->reason ?? '—' }}
                    </div>
                </div>

                </div>

            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                Причина возврата
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <label for="comment" class="form-label">Комментарий</label>
                    <textarea
                        name="comment"
                        id="comment"
                        rows="4"
                        class="form-control"
                    >{{ old('comment', $customerReturn->comment) }}</textarea>
                </div>

                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label for="closed_at" class="form-label">Дата закрытия</label>
                        <input
                            type="date"
                            name="closed_at"
                            id="closed_at"
                            class="form-control"
                            value="{{ old('closed_at', $customerReturn->closed_at ? \Carbon\Carbon::parse($customerReturn->closed_at)->format('Y-m-d') : '') }}"
                        >
                    </div>

                    <div class="col-md-4">
                                    <label class="form-label">Общий статус возврата</label>
                                    <select name="status" class="form-select">
                                        <option value="pending">В работе</option>
                                        <option value="completed">Завершен</option>
                                        <option value="cancelled">Отменен</option>
                                    </select>
                                </div>

                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <button type="submit" class="btn btn-primary">
                            Сохранить изменения
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </form>

</div>
@endsection