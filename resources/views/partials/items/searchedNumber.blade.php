                    @foreach ($items as $searchItem)
                        <div class="requestPartNumberContainer-item" data-price="{{ (int) ($searchItem['priceWithMargine'] ?? 0) }}" data-brand="{{ strtoupper(trim($searchItem['brand'] ?? '')) }}">
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
                                        {{ $searchItem['supplier_name'] }}
                                    @else
                                        {{ $searchItem['supplier_city'] }}
                                    @endif
                                @else
                                {{ $searchItem['supplier_city'] }}
                                @endauth
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">
                                {{ $searchItem['brand'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">
                                {{ $searchItem['article'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-name">
                                {{ $searchItem['name'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                                @if(array_key_exists('info',$searchItem))
                                    <i class="fas fa-circle-info spare-part-info-show" style="color:#042D4D;font-size:18px;cursor:pointer;"></i>

                                    <div class="info-block">
                                        <div class="block-info-close-wrapper">
                                            <button type="button" class="btn-close block-info-item-close" aria-label="Close"></button>
                                        </div>
                                        <div class="info-block-pictures">
                                            <div class="info-block-pictures-name">
                                                <div class="info-block-pictures-name-header">
                                                    {{ $searchItem['name'] }}
                                                </div>
                                                <div class="info-block-pictures-name-brand">
                                                    <span style="color:#bbb"> Брэнд: </span> {{ $searchItem['brand'] }}
                                                </div>
                                                <div class="info-block-pictures-name-article">
                                                    <span style="color:#bbb"> Артикул: </span> {{ $searchItem['article'] }}
                                                </div>
                                            </div>
                                            <div id="carouselExampleControls-searched-{{ $searchItem['article'] }}" class="carousel slide carouselExampleControls" data-bs-ride="carousel">
                                                <div class="carousel-inner">
                                                    @if (!empty($searchItem['info']['pictures']))
                                                        @if (gettype($searchItem['info']['pictures']) == 'string')
                                                            <div class="carousel-item active">
                                                                <img src="{{ $searchItem['info']['pictures'] }}" class="carousel-item-img" alt="sparepart-picture">
                                                            </div>
                                                        @else
                                                            @foreach($searchItem['info']['pictures'] as $pic_number => $picture_address)
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
                                                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls-searched-{{ $searchItem['article'] }}" data-bs-slide="prev">
                                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                    <span class="visually-hidden">Previous</span>
                                                </button>
                                                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls-searched-{{ $searchItem['article'] }}" data-bs-slide="next">
                                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                    <span class="visually-hidden">Next</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="info-block-information">
                                            <ul class="nav nav-tabs" id="productTabs-searched-{{ $searchItem['article'] }}" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active"
                                                            data-bs-toggle="tab"
                                                            data-bs-target="#description-searched-{{ $searchItem['article'] }}"
                                                            type="button"
                                                            role="tab">
                                                        Описание
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link"
                                                            data-bs-toggle="tab"
                                                            data-bs-target="#original-searched-{{ $searchItem['article'] }}"
                                                            type="button"
                                                            role="tab">
                                                        Оригинальные номера
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link"
                                                            data-bs-toggle="tab"
                                                            data-bs-target="#usage-searched-{{ $searchItem['article'] }}"
                                                            type="button"
                                                            role="tab">
                                                        Применение в автомобилях
                                                    </button>
                                                </li>
                                            </ul>
                                            <div class="tab-content mt-3">
                                                <div class="tab-pane fade show active info-description"
                                                    id="description-searched-{{ $searchItem['article'] }}"
                                                    role="tabpanel">
                                                    <ul class="info-description-sizes">
                                                        <li><b>Размеры</b></li>
                                                        <li>Ширина: {{ $searchItem['info']['params']['sizes']['width'] ?? '' }}</li>
                                                        <li>Высота: {{ $searchItem['info']['params']['sizes']['height'] ?? '' }}</li>
                                                        <li>Толщина: {{ $searchItem['info']['params']['sizes']['depth'] ?? '' }}</li>
                                                    </ul>
                                                </div>
                                                <div class="tab-pane fade info-oem-numbers"
                                                    id="original-searched-{{ $searchItem['article'] }}"
                                                    role="tabpanel">
                                                    @if (array_key_exists('OEM', $searchItem['info']['params']))
                                                        @foreach($searchItem['info']['params']['OEM'] as $oem_number)
                                                            {{ $oem_number }}
                                                        @endforeach
                                                    @endif
                                                </div>
                                                <div class="tab-pane fade"
                                                    id="usage-searched-{{ $searchItem['article'] }}"
                                                    role="tabpanel">
                                                    <p></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <i class="fas fa-circle-info spare-part-info-lookup"
                                       data-article="{{ $searchItem['article'] }}"
                                       data-brand="{{ $searchItem['brand'] }}"
                                       style="color:#042D4D;font-size:18px;cursor:pointer;"></i>
                                @endif
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-return">
                                @php $isReturnable = $searchItem['returnable'] ?? true; @endphp
                                <i class="fas fa-triangle-exclamation {{ $isReturnable ? 'text-success' : 'text-warning' }}"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="{{ $isReturnable
                                       ? 'Возврат возможен в течение 14 дней с момента получения товара в ПВЗ при сохранении товарного вида: целая чистая упаковка, без следов установки/эксплуатации, при наличии чека.'
                                       : 'Поставщик — из РФ или ОАЭ, международный возврат по этой позиции невозможен.' }}"></i>
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-delivery">
                                @php
                                    $ds = $searchItem['deliveryStart'] ?? '';
                                    $isToday = $ds == 'в офисе' || $ds == '2 часа' || (strtotime($ds) && date('Y-m-d', strtotime($ds)) == date('Y-m-d'));
                                    $isDate = !$isToday && strtotime($ds);
                                    $days = $isDate ? max(1, (int) ceil((strtotime($ds) - time()) / 86400)) : 0;
                                @endphp

                                @if ($isToday)
                                    <span class="badge" style="background:#d1e7dd;color:#0a6640;padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:600;min-width:80px;text-align:center;display:inline-block;">
                                        {{ $ds == 'в офисе' ? 'в офисе' : '2 часа' }}
                                    </span>
                                @elseif ($isDate)
                                    <span class="badge" style="background:transparent;color:#6c757d;border:1px solid #dee2e6;padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:500;min-width:80px;text-align:center;display:inline-block;">
                                        {{ $days }} дн.
                                    </span>
                                @else
                                    <span class="text-muted small">уточняйте</span>
                                @endif
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-count">
                                {{ $searchItem['qty']  }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-price stock-item-price">
                                {{ number_format($searchItem['priceWithMargine'], 0, '.', ' ') }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-cart">
                                <div class="stock-item-cart">
                                    <div class="stock-item-cart-btn">
                                        <i class="fas fa-cart-shopping stock-item-cart-img" style="color: #042D4D; font-size: 20px;"></i>
                                    </div>
                                    <div class="stock-item-cart-qty">
                                        <input type='number' value="1" min="1" max="{{ $searchItem['qty'] }}" class="form-control">
                                    </div>
                                    <input type="hidden" value="{{ $searchItem['price'] }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
