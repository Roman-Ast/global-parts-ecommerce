                @foreach ($items as $index => $crossItem)
                <div class="requestPartNumberContainer-item" data-price="{{ (int) ($crossItem['priceWithMargine'] ?? 0) }}" data-brand="{{ strtoupper(trim($crossItem['brand'] ?? '')) }}">
                    @auth
                    @if(auth()->user()->user_role == "admin")
                        <div class="form-check">
                            <input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;">
                        </div>
                    @endif
                    @endauth

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth
                            @if (auth()->user()->user_role == "admin")
                                {{ $crossItem['supplier_name'] }}
                            @else
                                {{ $crossItem['supplier_city'] }}
                            @endif
                        @else
                            {{ $crossItem['supplier_city'] }}
                        @endauth
                    </div>

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">
                        {{ $crossItem['brand'] }}
                    </div>

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">
                        {{ $crossItem['article'] }}
                    </div>

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">
                        {{ $crossItem['name'] }}
                    </div>

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        <i class="fas fa-circle-info spare-part-info-lookup"
                           data-article="{{ $crossItem['article'] }}"
                           data-brand="{{ $crossItem['brand'] }}"
                           style="color:#042D4D;font-size:18px;cursor:pointer;"></i>
                    </div>

                    <div class="requestPartNumberContainer-item-entity requestPartNumber-return">
                        @php $isReturnable = $crossItem['returnable'] ?? true; @endphp
                        <i class="fas fa-triangle-exclamation {{ $isReturnable ? 'text-success' : 'text-warning' }}"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="{{ $isReturnable
                               ? 'Возврат возможен в течение 14 дней с момента получения товара в ПВЗ при сохранении товарного вида: целая чистая упаковка, без следов установки/эксплуатации, при наличии чека.'
                               : 'Поставщик — из РФ или ОАЭ, международный возврат по этой позиции невозможен.' }}"></i>
                    </div>

                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery">
                        @php
                            $dt = $crossItem['delivery_time'] ?? '';
                            $days = strtotime($dt) ? max(1, (int) ceil((strtotime($dt) - time()) / 86400)) : null;
                        @endphp
                        @if ($days)
                            <span class="badge" style="background:transparent;color:#6c757d;border:1px solid #dee2e6;padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:500;min-width:80px;text-align:center;display:inline-block;">
                                {{ $days }} дн.
                            </span>
                        @else
                            <span class="text-muted small">уточняйте</span>
                        @endif
                    </div>

                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-qty">
                                {{ $stockItem['qty'] }}
                            </div>
                        @endforeach
                    </div>

                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-price">
                                {{ number_format($crossItem['priceWithMargine'], 0, '.', ' ') }}
                            </div>
                        @endforeach
                    </div>

                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn">
                                    <i class="fas fa-cart-shopping stock-item-cart-img" style="color: #042D4D; font-size: 20px;"></i>
                                </div>
                                <div class="stock-item-cart-qty">
                                    <input type='number' value="1" min="1" max="{{ $stockItem['qty'] }}" class="form-control">
                                </div>
                                <input type="hidden" value="{{ $crossItem['price'] }}">
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
