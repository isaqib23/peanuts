@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('transaction::transactions.transactions'))

    <li class="active">{{ trans('transaction::transactions.transactions') }}</li>
@endcomponent

@section('content')
    <div class="box box-primary">
        <div class="box-body index-table" id="transactions-table"">
            @component('admin::components.table')
                @slot('thead')
                    <tr>
                        <th>{{ trans('transaction::transactions.table.order_id') }}</th>
                        <th>{{ trans('transaction::transactions.table.transaction_id') }}</th>
                        <th>{{ trans('transaction::transactions.table.payment_method') }}</th>
                        <th data-sort>{{ trans('admin::admin.table.created') }}</th>
                    </tr>
                @endslot
            @endcomponent
        </div>
    </div>
@endsection

@push('scripts')
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>
    
    <script>
        DataTable.setRoutes('#transactions-table .table', {
            index: '{{ "admin.transactions.index" }}',
        });

        new DataTable('#transactions-table .table', {
            dom: 'Blfrtip',
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5'
            ],
            columns: [
                { data: 'order_id' },
                { data: 'transaction_id' },
                { data: 'payment_method' },
                { data: 'created', name: 'created_at' },
            ],
        });
    </script>
@endpush
