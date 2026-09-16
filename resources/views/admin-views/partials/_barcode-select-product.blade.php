@if(isset($selectedProducts))
    @foreach($selectedProducts as $key=>$product)
        <div class="select-product-item media gap-3 p-2 align-items-center border rounded position-relative w-100">
            <input type="text" class="product_id" name="product_id[]" value="{{$product['id']}}" required hidden>
            <img class="avatar avatar-xl border" width="75"
                 src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'backend-basic') }}" alt="">
            <div class="media-body d-flex flex-column gap-1">
                <h6 class="fz-13 mb-1 product-name">{{$product['name']}}</h6>
                <div class="fz-10">
                    @if ($product->code)
                        {{translate('code')}} : {{$product->code}}
                    @else
                        <a class="text-danger" href="{{route('admin.products.update', [$product['id']]) }}" target="_blank">
                            {{translate('update_your_product_code')}}
                        </a>
                    @endif
                </div>
                <div class="fz-10">{{translate('price').' '.':'.' '}}<span>{{setCurrencySymbol(usdToDefaultCurrency(amount: $product['unit_price']))}}</span></div>
            </div>
            <div style="width: 110px;">
                <label class="fz-10 mb-1 text-capitalize">{{translate('quantity')}}</label>
                <input type="number" name="qty[]" class="form-control barcode-qty-input" min="1" max="270"
                       value="4" data-product-id="{{$product['id']}}">
            </div>
            <button class="remove-selected-product" type="button" data-product-id="{{$product['id']}}">
                <i class="tio-clear-circle"></i>
            </button>
        </div>
    @endforeach
@endif
