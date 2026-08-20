                @foreach ($items as $index => $crossItem)
                <div class="requestPartNumberContainer-item" data-price="{{ (int) ($crossItem['priceWithMargine'] ?? 0) }}" data-brand="{{ strtoupper(trim($crossItem['brand'] ?? '')) }}">

                    @if(auth()->user()->user_role == "admin")
                        <div class="form-check">
                            <input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;">
                        </div>
                    @endif
                    
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
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery" style="background:{{ $crossItem['supplier_color']}};color:#111">
                        {{ $crossItem['delivery_time'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">
                            @if ($crossItem['qty'] > 10)
                                >10
                            @else
                                {{ $crossItem['qty'] }}
                            @endif
                        </div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        <div class="stock-item stock-item-price">
                            {{ number_format($crossItem['priceWithMargine'], 0, '.', ' ') }}
                        </div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        <div class="stock-item-cart">
                            <div class="stock-item-cart-btn">
                                <i class="fas fa-cart-shopping stock-item-cart-img" style="color: #042D4D; font-size: 20px;"></i>
                            </div>
                            <div class="stock-item-cart-qty">
                                <input type='number' value="1" min="1" max="{{ $crossItem['qty'] }}" class="form-control">
                            </div>
                            <input type="hidden" value="{{ $crossItem['price'] }}">
                        </div>
                    </div>
                </div>
                @endforeach
