@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('admin::resource.create', ['resource' => trans('suppliers::suppliers.suppliers')]))

    <li><a href="{{ route('admin.suppliers.index') }}">{{ trans('suppliers::suppliers.suppliers') }}</a></li>
    <li class="active">{{ trans('admin::resource.create', ['resource' => trans('suppliers::suppliers.suppliers')]) }}</li>
@endcomponent

@section('content')
    <form method="POST" action="{{ route('admin.suppliers.store') }}" class="form-horizontal" id="suppliers-create-form" novalidate>
        {{ csrf_field() }}

        {!! $tabs->render(compact('supplier')) !!}
    </form>
@endsection

