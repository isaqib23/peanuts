@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('admin::resource.show', ['resource' => trans('order::orders.order')]))

        <li><a href="{{ route('admin.orders.index') }}">{{ trans('order::orders.orders') }}</a></li>
    <li class="active">{{ trans('admin::resource.show', ['resource' => trans('order::orders.order')]) }}</li>
@endcomponent

@section('content')
    <div class="order-wrapper">
        <h1>sdsdsd</h1>
    </div>
@endsection

