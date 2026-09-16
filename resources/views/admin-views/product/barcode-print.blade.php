@extends('layouts.back-end.app')

@section('title', translate('generate_Barcode') . ' ' . date('Y/m/d'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/barcode.css') }}"/>
@endpush

@section('content')
    <div class="row m-2 show-div pt-3">
        <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <h2 class="h1 mb-0 text-capitalize d-flex gap-2">
                    <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/inhouse-product-list.png') }}" alt="">
                    {{ translate('generate_Barcode') }}
                </h2>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.products.barcode-generator') }}" class="btn btn-outline-danger">
                        {{ translate('back') }}
                    </a>
                    <button type="button" id="print_bar" data-value="print-area"
                            class="btn btn-outline--primary action-print-invoice">
                        {{ translate('print') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mt-5 p-4">
            <h1 class="style-one-br show-div2">
                {{ translate("this_page_is_for_A4_size_page_printer_so_it_will_not_be_visible_in_smaller_devices.") }}
            </h1>
        </div>
    </div>

    <div id="print-area" class="show-div pb-5">
        @forelse($barcodePages as $page)
            <div class="barcode-a4">
                @foreach($page as $product)
                    <div class="item style24">
                        <span class="barcode_site text-capitalize">
                            {{ getWebConfig(name: 'company_name') }}
                        </span>
                        <span class="barcode_name text-capitalize">
                            {{ Str::limit($product->name, 20) }}
                        </span>
                        <div class="barcode_price text-capitalize">
                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->unit_price), currencyCode: getCurrencyCode()) }}
                        </div>

                        @if ($product->code !== null)
                            <div class="barcode_image d-flex justify-content-center">
                                {!! DNS1D::getBarcodeHTML($product->code, 'C128') !!}
                            </div>
                            <div class="barcode_code text-capitalize">
                                {{ translate('code') }} : {{ $product->code }}
                            </div>
                        @else
                            <p class="text-danger">
                                {{ translate('please_update_product_code') }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            @include('layouts.back-end._empty-state', ['text' => 'no_product_select_yet'])
        @endforelse
    </div>
@endsection
