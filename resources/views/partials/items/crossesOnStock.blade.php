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
                        <input type="hidden" class="requestPartNumber-supplier-token" value="{{ $crossItem['supplier_token'] ?? '' }}">
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
                        
                        @if (array_key_exists('info', $crossItem))
                            <i class="fas fa-circle-info spare-part-info-show" style="color:#042D4D;font-size:18px;cursor:pointer;"></i>

                            <div class="info-block">
                                <div class="block-info-close-wrapper">
                                    <button type="button" class="btn-close block-info-item-close" aria-label="Close"></button>
                                </div>
                                <div class="info-block-pictures">
                                        <div class="info-block-pictures-name">
                                            <div class="info-block-pictures-name-header">
                                                {{ $crossItem['name'] }}
                                            </div>
                                            <div class="info-block-pictures-name-brand">
                                                <span style="color:#bbb"> Брэнд: </span> {{ $crossItem['brand'] }}
                                            </div>
                                            <div class="info-block-pictures-name-article">
                                                <span style="color:#bbb"> Артикул: </span> {{ $crossItem['article'] }}
                                            </div>
                                        </div>
                                        <div id="carouselExampleControls-{{ $crossItem['article'] }}" class="carousel slide carouselExampleControls" data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            @if (!empty($crossItem['info']['pictures']))
                                                @if (gettype($crossItem['info']['pictures']) == 'string')
                                                    <div class="carousel-item active">
                                                        <img src="{{ $crossItem['info']['pictures'] }}" class="carousel-item-img" alt="sparepart-picture">
                                                    </div>
                                                @else
                                                    @foreach($crossItem['info']['pictures'] as $pic_number => $picture_address)
                                                        @if($pic_number == 0)
                                                            <div class="carousel-item active">
                                                                <img src="{{ $picture_address }}" class="carousel-item-img" alt="sparepart-picture">
                                                            </div>
                                                        @else
                                                            <div class="carousel-item">
                                                                <img src="{{ $picture_address }}" class="carousel-item-img" alt="sparepart-picture">
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @endif
                                        </div>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls-{{ $crossItem['article'] }}" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls-{{ $crossItem['article'] }}" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="info-block-information">
                                    <!-- NAV TABS -->
                                    <ul class="nav nav-tabs" id="productTabs-{{ $crossItem['article'] }}" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active"
                                                    id="description-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#description-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Описание
                                            </button>
                                        </li>

                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link"
                                                    id="original-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#original-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Оригинальные номера
                                            </button>
                                        </li>

                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link"
                                                    id="usage-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#usage-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Применение в автомобилях
                                            </button>
                                        </li>
                                    </ul>

                                    <!-- TAB CONTENT -->
                                    <div class="tab-content mt-3" id="productTabsContent-{{ $crossItem['article'] }}" class="productTabsContent">

                                        <div class="tab-pane fade show active info-description"
                                            id="description-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                            <ul class="info-description-sizes">
                                                <li>
                                                    <b>Размеры</b>
                                                </li>
                                                <li>Ширина: {{ $crossItem['info']['params']['sizes']['width'] ?? '' }}</li>
                                                <li>Высота: {{ $crossItem['info']['params']['sizes']['height'] ?? '' }}</li>
                                                <li>Толщина: {{ $crossItem['info']['params']['sizes']['depth'] ?? ''}}</li>
                                            </ul>
                                        </div>

                                        <div class="tab-pane fade info-oem-numbers"
                                            id="original-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                                @if (array_key_exists('OEM', $crossItem['info']['params']))
                                                    @foreach($crossItem['info']['params']['OEM'] as $oem_number)
                                                        {{ $oem_number }}
                                                    @endforeach
                                                @endif
                                                
                                        </div>

                                        <div class="tab-pane fade"
                                            id="usage-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                            <p>
                                                
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @else
                            <i class="fas fa-circle-info spare-part-info-lookup"
                               data-article="{{ $crossItem['article'] }}"
                               data-brand="{{ $crossItem['brand'] }}"
                               style="color:#042D4D;font-size:18px;cursor:pointer;"></i>
                        @endif
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
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery parts-on-stock">
                        <span class="badge gp-delivery-badge" style="padding:5px 10px;border-radius:6px;font-size:0.85rem;display:inline-block;min-width:80px;text-align:center;">
                            <i class="fas fa-clock"></i> {{ $crossItem['delivery_time'] }}
                        </span>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">
                            {{ $crossItem['qty'] }}
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
