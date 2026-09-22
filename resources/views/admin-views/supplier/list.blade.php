@extends('layouts.back-end.app')

@section('title', translate('suppliers'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h1 mb-1 text-capitalize d-flex gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/bulk-import.png')}}" alt="">
                {{translate('suppliers')}}
            </h2>
            <button type="button" class="btn btn--primary" data-toggle="modal" data-target="#supplier-modal"
                    onclick="openAddSupplierModal()">
                <i class="tio-add"></i> {{translate('add_new_supplier')}}
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{route('admin.products.supplier.list')}}">
                    <div class="row align-items-end mb-3">
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="title-color">{{translate('search_by_name_shop_or_phone')}}</label>
                                <input type="text" class="form-control" name="searchValue"
                                       value="{{ $searchValue }}"
                                       placeholder="{{translate('search_by_name_shop_or_phone')}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <button type="submit" class="btn btn--primary w-100">{{translate('filter')}}</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>{{translate('name')}}</th>
                            <th>{{translate('shop_name')}}</th>
                            <th>{{translate('phone')}}</th>
                            <th>{{translate('email')}}</th>
                            <th>{{translate('address')}}</th>
                            <th class="text-center">{{translate('status')}}</th>
                            <th class="text-center">{{translate('action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($suppliers as $supplier)
                            <tr>
                                <td>{{ $supplier->name }}</td>
                                <td>{{ $supplier->shop_name ?? '-' }}</td>
                                <td>{{ $supplier->phone ?? '-' }}</td>
                                <td>{{ $supplier->email ?? '-' }}</td>
                                <td>{{ $supplier->address ?? '-' }}</td>
                                <td class="text-center">
                                    <label class="switcher">
                                        <input type="checkbox" class="switcher_input supplier-status-change"
                                               data-id="{{ $supplier->id }}" {{ $supplier->status == 1 ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline--primary btn-sm edit-supplier-btn"
                                            data-id="{{ $supplier->id }}"
                                            data-name="{{ $supplier->name }}"
                                            data-shop_name="{{ $supplier->shop_name }}"
                                            data-phone="{{ $supplier->phone }}"
                                            data-email="{{ $supplier->email }}"
                                            data-address="{{ $supplier->address }}"
                                            data-toggle="modal" data-target="#supplier-modal">
                                        <i class="tio-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm delete-supplier-btn"
                                            data-id="{{ $supplier->id }}">
                                        <i class="tio-delete"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">{{translate('no_data_found')}}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $suppliers->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="supplier-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplier-modal-title">{{translate('add_new_supplier')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="supplier-form" method="POST" action="{{route('admin.products.supplier.add')}}">
                        @csrf
                        <input type="hidden" name="id" id="supplier-id">
                        <div class="form-group">
                            <label class="input-label">{{translate('name')}} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="supplier-name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('shop_name')}}</label>
                            <input type="text" name="shop_name" id="supplier-shop-name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('phone')}}</label>
                            <input type="text" name="phone" id="supplier-phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('email')}}</label>
                            <input type="email" name="email" id="supplier-email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{translate('address')}}</label>
                            <textarea name="address" id="supplier-address" class="form-control" rows="2"></textarea>
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
        const supplierAddUrl = "{{ route('admin.products.supplier.add') }}";
        const supplierUpdateUrlBase = "{{ route('admin.products.supplier.update') }}";

        function openAddSupplierModal() {
            $('#supplier-modal-title').text("{{translate('add_new_supplier')}}");
            $('#supplier-form').attr('action', supplierAddUrl);
            $('#supplier-form')[0].reset();
            $('#supplier-id').val('');
        }

        $(document).on('click', '.edit-supplier-btn', function () {
            $('#supplier-modal-title').text("{{translate('edit_supplier')}}");
            $('#supplier-form').attr('action', supplierUpdateUrlBase);
            $('#supplier-id').val($(this).data('id'));
            $('#supplier-name').val($(this).data('name'));
            $('#supplier-shop-name').val($(this).data('shop_name'));
            $('#supplier-phone').val($(this).data('phone'));
            $('#supplier-email').val($(this).data('email'));
            $('#supplier-address').val($(this).data('address'));
        });

        $(document).on('change', '.supplier-status-change', function () {
            const id = $(this).data('id');
            const status = $(this).is(':checked') ? 1 : 0;
            $.ajax({
                url: "{{ route('admin.products.supplier.status-update') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    id: id,
                    status: status,
                },
            });
        });

        $(document).on('click', '.delete-supplier-btn', function () {
            const id = $(this).data('id');
            const $row = $(this);
            if (!confirm("{{translate('are_you_sure_you_want_to_delete_this_supplier?')}}")) {
                return;
            }
            $.ajax({
                url: "{{ url('admin/products/supplier/delete') }}/" + id,
                type: 'DELETE',
                data: {_token: "{{ csrf_token() }}"},
                success: function () {
                    location.reload();
                },
            });
        });
    </script>
@endpush
