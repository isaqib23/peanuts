@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('suppliers::suppliers.suppliers'))

    <li class="active">{{ trans('suppliers::suppliers.suppliers') }}</li>
@endcomponent

@component('admin::components.page.index_table')
    @slot('buttons', ['create'])
    @slot('resource', 'suppliers')
    @slot('name', trans('suppliers::suppliers.supplier'))

    @component('admin::components.table')
        @slot('thead')
            <tr>
                @include('admin::partials.table.select_all')

                <th>{{ trans('admin::admin.table.id') }}</th>
                <th>{{ trans('suppliers::suppliers.table.name') }}</th>
                <th>{{ trans('suppliers::suppliers.table.email') }}</th>
                <th>{{ trans('suppliers::suppliers.table.phone') }}</th>
                <th>{{ trans('suppliers::suppliers.table.fax') }}</th>
                <th>{{ trans('suppliers::suppliers.table.website') }}</th>
                <th>{{ trans('suppliers::suppliers.table.address') }}</th>
            </tr>
        @endslot
    @endcomponent
@endcomponent
@push('scripts')
    <script>
        new DataTable('#suppliers-table .table', {
            columns: [
                { data: 'checkbox', orderable: false, searchable: false, width: '3%' },
                { data: 'id', width: '5%' },
                { data: 'name', name: 'name', orderable: true },
                { data: 'email', name: 'email' },
                { data: 'phone', name: 'phone'},
                { data: 'fax', name: 'fax', orderable: false, defaultContent: '' },
                { data: 'website', name: 'website', orderable: false, defaultContent: '' },
                { data: 'address', name: 'address' },
                { data: 'phone', name: 'phone' },
                { data: 'phone', name: 'phone' }
            ],
        });
    </script>
@endpush
