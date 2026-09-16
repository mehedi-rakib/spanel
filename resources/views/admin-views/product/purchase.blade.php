@extends('layouts.back-end.app')

@section('title', translate('purchase_Product'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png')}}" alt="">
                {{translate('purchase_Product')}}
            </h2>
        </div>

        <div class="row text-start">
            <div class="col-12">
                <div class="card card-body">
                    <p class="mb-0">{{translate('search_or_filter_products_below,_enter_the_quantity_received_and_unit_cost_for_each_product,_then_submit_to_add_them_to_stock.')}}</p>
                </div>
            </div>

            <div class="col-12 mt-2">
                <form method="GET" action="{{route('admin.products.purchase')}}">
                    <div class="card card-body">
                        <div class="row align-items-end">
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label class="title-color">{{translate('category')}}</label>
                                    <select class="js-select2-custom form-control" name="category_id">
                                        <option value="">{{translate('all_categories')}}</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category['id'] }}" {{ request('category_id') == $category['id'] ? 'selected' : '' }}>{{ $category['defaultName'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label class="title-color">{{translate('brand')}}</label>
                                    <select class="js-select2-custom form-control" name="brand_id">
                                        <option value="">{{translate('all_brands')}}</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand['id'] }}" {{ request('brand_id') == $brand['id'] ? 'selected' : '' }}>{{ $brand['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <div class="form-group">
                                    <label class="title-color">{{translate('search_by_name_or_SKU')}}</label>
                                    <input type="text" class="form-control" name="searchValue"
                                           value="{{ request('searchValue') }}"
                                           placeholder="{{translate('search_by_name_or_SKU')}}">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <button type="submit" class="btn btn--primary w-100">{{translate('search')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-12 mt-3">
                @if($products->count() > 0)
                    <form method="POST" action="{{route('admin.products.purchase-submit')}}">
                        @csrf
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-group mb-0">
                                            <label class="title-color">{{translate('reference_no')}}</label>
                                            <input type="text" class="form-control" name="reference_no"
                                                   placeholder="{{translate('ex').': PO-1001'}}">
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr>
                                            <th>{{translate('product')}}</th>
                                            <th>{{translate('product_SKU')}}</th>
                                            <th>{{translate('current_stock_qty')}}</th>
                                            <th style="min-width:140px">{{translate('quantity_received')}}</th>
                                            <th style="min-width:140px">{{translate('unit_cost')}}</th>
                                            <th style="min-width:180px">{{translate('note')}}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($products as $product)
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="product_id[]" value="{{ $product->id }}">
                                                    {{ $product->name }}
                                                </td>
                                                <td>{{ $product->code }}</td>
                                                <td>{{ $product->current_stock }}</td>
                                                <td>
                                                    <input type="number" min="0" step="1" class="form-control"
                                                           name="qty[]" value="0">
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control"
                                                           name="unit_cost[]" value="{{ $product->purchase_price }}">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="note[]">
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn--primary px-4">{{translate('submit_purchase')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="card card-body text-center text-muted">
                        {{translate('use_the_filters_above_to_find_products_to_purchase.')}}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
