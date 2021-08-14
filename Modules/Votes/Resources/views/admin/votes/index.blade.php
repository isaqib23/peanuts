@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('votes::votes.votes'))

    <li class="active">{{ trans('votes::votes.votes') }}</li>
@endcomponent

@component('admin::components.page.index_table')
    @slot('buttons', ['create'])
    @slot('resource', 'votes')
    @slot('name', trans('votes::votes.vote'))

    @component('admin::components.table')
        @slot('thead')
            <tr>
                @include('admin::partials.table.select_all')

                <th>{{ trans('admin::admin.table.id') }}</th>
                <th>{{ trans('votes::votes.table.product_1') }}</th>
                <th>{{ trans('votes::votes.table.product_2') }}</th>
                <th data-sort>{{ trans('admin::admin.table.created') }}</th>
            </tr>
        @endslot
    @endcomponent
@endcomponent
@push('scripts')
    <script>
        new DataTable('#votes-table .table', {
            columns: [
                { data: 'checkbox', orderable: false, searchable: false, width: '3%' },
                { data: 'id', width: '5%' },
                //{ data: 'product A', name: 'product_1', orderable: false, defaultContent: '' },
                //{ data: 'product B', name: 'product_2', orderable: false, defaultContent: '' },
                { data: 'created', name: 'product_1' },
                { data: 'created', name: 'product_1' },
                { data: 'created', name: 'created_at' },
            ],
        });
    </script>
@endpush
