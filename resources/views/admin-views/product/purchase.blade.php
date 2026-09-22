@extends('layouts.back-end.app')

@section('title', translate('purchase_Product'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png')}}" alt="">
                {{translate('purchase_Product')}}
            </h2>
            <a href="{{route('admin.products.purchase-list')}}" class="btn btn-outline--primary">
                <i class="tio-view-list"></i> {{translate('purchase_list')}}
            </a>
        </div>

        <form method="POST" action="{{route('admin.products.purchase-submit')}}" id="purchase-form">
            @csrf
            <div class="row text-start">
                <div class="col-12">
                    <div class="card card-body">
                        <div class="row align-items-end">
                            <div class="col-md-5 col-lg-5">
                                <div class="form-group mb-0">
                                    <label class="title-color">{{translate('supplier')}} <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-2">
                                        <select id="purchase-supplier-select" name="supplier_id" class="form-control" required>
                                            <option value="">{{translate('select_supplier')}}</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->phone ? ' ('.$supplier->phone.')' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-success text-nowrap" id="add_new_supplier" type="button"
                                                data-toggle="modal" data-target="#add-supplier" title="{{translate('add_new_supplier')}}">
                                            <i class="tio-add"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-lg-4">
                                <div class="form-group mb-0 position-relative">
                                    <label class="title-color">{{translate('search_product')}}</label>
                                    <input type="text" id="purchase-product-search" autocomplete="off"
                                           class="form-control" placeholder="{{translate('search_by_product_name_or_SKU')}}">
                                    <div id="purchase-product-search-results" class="card shadow position-absolute w-100 d-none"
                                         style="z-index: 1000; max-height: 320px; overflow-y: auto; top: 100%; left: 0;"></div>
                                </div>
                            </div>
                            <div class="col-md-3 col-lg-3">
                                <div class="form-group mb-0">
                                    <label class="title-color">{{translate('reference_no')}}</label>
                                    <input type="text" class="form-control" name="reference_no"
                                           placeholder="{{translate('auto_generated_if_left_blank')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="purchase-items-table">
                                    <thead>
                                    <tr>
                                        <th>{{translate('product')}}</th>
                                        <th>{{translate('product_SKU')}}</th>
                                        <th>{{translate('current_stock_qty')}}</th>
                                        <th style="min-width:120px">{{translate('quantity_received')}}</th>
                                        <th style="min-width:140px">{{translate('unit_cost')}}</th>
                                        <th style="min-width:180px">{{translate('note')}}</th>
                                        <th style="width:60px"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="purchase-items-body">
                                    <tr id="purchase-items-empty-row">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            {{translate('search_and_select_products_above_to_add_them_to_this_purchase.')}}
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn--primary px-4" id="purchase-submit-btn" disabled>
                                    {{translate('submit_purchase')}}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <template id="purchase-item-row-template">
        <tr class="purchase-item-row" data-product-id="__ID__">
            <td>
                <input type="hidden" name="product_id[]" value="__ID__">
                <span class="font-weight-semibold">__NAME__</span>
            </td>
            <td>__CODE__</td>
            <td class="purchase-item-stock">__STOCK__</td>
            <td>
                <input type="number" min="1" step="1" class="form-control purchase-item-qty" name="qty[]" value="1">
            </td>
            <td>
                <input type="number" min="0" step="0.01" class="form-control purchase-item-cost" name="unit_cost[]" value="__COST__">
            </td>
            <td>
                <input type="text" class="form-control" name="note[]">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-purchase-item">
                    <i class="tio-delete"></i>
                </button>
            </td>
        </tr>
    </template>

    <div class="modal fade" id="add-supplier" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('add_new_supplier') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="add-supplier-form">
                        @csrf
                        <div class="form-group">
                            <label class="input-label">{{translate('name')}} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="new-supplier-name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('shop_name')}}</label>
                            <input type="text" name="shop_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('phone')}}</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('email')}}</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('address')}}</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            const searchUrl = "{{ route('admin.products.purchase-search') }}";
            const $searchInput = $('#purchase-product-search');
            const $resultsBox = $('#purchase-product-search-results');
            const $itemsBody = $('#purchase-items-body');
            const $emptyRow = $('#purchase-items-empty-row');
            const $submitBtn = $('#purchase-submit-btn');
            const rowTemplate = document.getElementById('purchase-item-row-template').innerHTML;
            let searchTimer = null;

            function escapeHtml(str) {
                return $('<div>').text(str ?? '').html();
            }

            function toggleSubmitState() {
                $submitBtn.prop('disabled', $itemsBody.find('.purchase-item-row').length === 0);
            }

            function hideResults() {
                $resultsBox.addClass('d-none').empty();
            }

            let activeRequest = null;

            $searchInput.on('keyup', function () {
                const value = $(this).val().trim();
                clearTimeout(searchTimer);
                if (activeRequest) {
                    activeRequest.abort();
                    activeRequest = null;
                }
                if (value.length < 2) {
                    hideResults();
                    return;
                }
                $resultsBox.removeClass('d-none').html(
                    '<div class="p-3 text-muted">{{translate('searching')}}&hellip;</div>'
                );
                searchTimer = setTimeout(function () {
                    activeRequest = $.get(searchUrl, {searchValue: value})
                        .done(function (response) {
                            const results = (response && response.results) || [];
                            if (results.length === 0) {
                                $resultsBox.removeClass('d-none').html(
                                    '<div class="p-3 text-muted">' + '{{translate('No_Product_Found')}}' + '</div>'
                                );
                                return;
                            }
                            let html = '';
                            results.forEach(function (product) {
                                html += '<div class="d-flex align-items-center gap-2 p-2 border-bottom cursor-pointer purchase-search-item"' +
                                    ' data-id="' + product.id + '"' +
                                    ' data-name="' + escapeHtml(product.text) + '"' +
                                    ' data-code="' + escapeHtml(product.code) + '"' +
                                    ' data-stock="' + product.current_stock + '"' +
                                    ' data-cost="' + product.purchase_price + '">' +
                                    '<img src="' + product.image + '" width="36" height="36" class="rounded border" alt="">' +
                                    '<div><div class="font-weight-semibold">' + escapeHtml(product.text) + '</div>' +
                                    '<small class="text-muted">' + escapeHtml(product.code) + ' &middot; {{translate('stock')}}: ' + product.current_stock + '</small></div>' +
                                    '</div>';
                            });
                            $resultsBox.removeClass('d-none').html(html);
                        })
                        .fail(function (xhr) {
                            console.error('Purchase product search failed', xhr.status, xhr.responseText);
                            $resultsBox.removeClass('d-none').html(
                                '<div class="p-3 text-danger">' +
                                '{{translate('something_went_wrong')}}' +
                                ' (HTTP ' + xhr.status + ')</div>'
                            );
                        });
                }, 300);
            });

            $(document).on('click', '.purchase-search-item', function () {
                const id = $(this).data('id');
                const $existingRow = $itemsBody.find('.purchase-item-row[data-product-id="' + id + '"]');

                if ($existingRow.length) {
                    $existingRow.find('.purchase-item-qty').val(function (i, val) {
                        return (parseInt(val || 0) + 1);
                    });
                } else {
                    $emptyRow.remove();
                    const rowHtml = rowTemplate
                        .split('__ID__').join(id)
                        .split('__NAME__').join(escapeHtml($(this).data('name')))
                        .split('__CODE__').join(escapeHtml($(this).data('code')))
                        .split('__STOCK__').join($(this).data('stock'))
                        .split('__COST__').join($(this).data('cost'));
                    $itemsBody.append(rowHtml);
                }

                toggleSubmitState();
                $searchInput.val('');
                hideResults();
            });

            $(document).on('click', '.remove-purchase-item', function () {
                $(this).closest('.purchase-item-row').remove();
                if ($itemsBody.find('.purchase-item-row').length === 0) {
                    $itemsBody.append($emptyRow);
                }
                toggleSubmitState();
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#purchase-product-search, #purchase-product-search-results').length) {
                    hideResults();
                }
            });

            $('#purchase-form').on('submit', function (e) {
                if ($itemsBody.find('.purchase-item-row').length === 0 || !$('#purchase-supplier-select').val()) {
                    e.preventDefault();
                }
            });

            $('#add-supplier-form').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('admin.products.supplier.add') }}",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function (response) {
                        if (response && response.supplier) {
                            $('#purchase-supplier-select').append(
                                new Option(response.supplier.text, response.supplier.id, true, true)
                            );
                        }
                        $('#add-supplier-form')[0].reset();
                        $('#add-supplier').modal('hide');
                    },
                    error: function (xhr) {
                        console.error('Add supplier failed', xhr.status, xhr.responseText);
                    },
                });
            });
        })();
    </script>
@endpush
