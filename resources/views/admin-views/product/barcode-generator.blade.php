@extends('layouts.back-end.app')

@section('title', translate('generate_Barcode'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4">
            <h2 class="h1 mb-0 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/inhouse-product-list.png')}}" alt="">
                {{translate('generate_Barcode')}}
            </h2>
            <p class="mb-0">{{translate('search_and_add_one_or_multiple_products_to_generate_and_print_their_SKU_barcodes.')}}</p>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form id="barcode-generator-form" action="{{route('admin.products.barcode-generator.print')}}"
                              method="POST" target="_blank">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="name" class="title-color">{{translate('select_products')}}</label>
                                    <div class="dropdown select-barcode-product-search w-100">
                                        <div class="search-form" data-toggle="dropdown" aria-expanded="false">
                                            <button type="button" class="btn"><i class="tio-down-ui"></i></button>
                                            <input type="text" class="js-form-search form-control search-bar-input search-barcode-product"
                                                   placeholder="{{translate('search_by_product_name_or_code').'...'}}">
                                        </div>
                                        <div class="dropdown-menu w-100 px-2">
                                            <div class="d-flex flex-column max-h-300 overflow-y-auto overflow-x-hidden search-barcode-result-box">
                                                @include('admin-views.partials._search-product', ['products' => $products])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="selected-barcode-products d-flex flex-column gap-2 mt-3" id="selected-barcode-products"></div>

                            <div class="d-flex flex-wrap gap-10 justify-content-end mt-4">
                                <button type="button" class="btn btn-secondary font-weight-bold px-4 reset-selected-barcode-products">
                                    {{translate('reset')}}
                                </button>
                                <button type="submit" id="generate-barcode-btn" class="btn btn--primary font-weight-bold px-4" disabled>
                                    {{translate('generate_&_print')}}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <span id="get-barcode-selected-products-route" data-action="{{route('admin.products.barcode-generator.selected-products')}}"></span>
@endsection

@push('script')
    <script>
        "use strict";
        (function () {
            let selectedIds = [];
            let quantities = {};
            const $searchInput = $('.search-barcode-product');
            const $resultBox = $('.search-barcode-result-box');
            const $selectedBox = $('#selected-barcode-products');
            const $generateBtn = $('#generate-barcode-btn');

            $searchInput.on('keyup', function () {
                let name = $(this).val();
                $.get($('#get-search-product-route').data('action'), {searchValue: name}, function (response) {
                    $resultBox.empty().html(response.result);
                });
            });

            $resultBox.on('click', '.select-product-item', function () {
                let productId = $(this).find('.product-id').text().trim();
                if (productId && selectedIds.indexOf(productId) === -1) {
                    selectedIds.push(productId);
                    if (!quantities[productId]) {
                        quantities[productId] = 4;
                    }
                    fetchSelectedProducts();
                }
            });

            $selectedBox.on('click', '.remove-selected-product', function () {
                let productId = String($(this).data('product-id'));
                selectedIds = selectedIds.filter(function (id) {
                    return id !== productId;
                });
                delete quantities[productId];
                fetchSelectedProducts();
            });

            $selectedBox.on('change', '.barcode-qty-input', function () {
                let productId = String($(this).data('product-id'));
                let value = parseInt($(this).val()) || 1;
                quantities[productId] = value;
            });

            $('.reset-selected-barcode-products').on('click', function () {
                selectedIds = [];
                quantities = {};
                $selectedBox.empty();
                toggleGenerateButton();
            });

            function fetchSelectedProducts() {
                if (selectedIds.length === 0) {
                    $selectedBox.empty();
                    toggleGenerateButton();
                    return;
                }
                $.ajax({
                    url: $('#get-barcode-selected-products-route').data('action'),
                    type: 'GET',
                    data: {productIds: selectedIds},
                    beforeSend: function () {
                        $("#loading").fadeIn();
                    },
                    success: function (response) {
                        $selectedBox.empty().html(response.result);
                        $selectedBox.find('.barcode-qty-input').each(function () {
                            let productId = String($(this).data('product-id'));
                            if (quantities[productId]) {
                                $(this).val(quantities[productId]);
                            }
                        });
                        toggleGenerateButton();
                    },
                    complete: function () {
                        $("#loading").fadeOut();
                    },
                });
            }

            function toggleGenerateButton() {
                $generateBtn.prop('disabled', $selectedBox.children().length === 0);
            }
        })();
    </script>
@endpush
