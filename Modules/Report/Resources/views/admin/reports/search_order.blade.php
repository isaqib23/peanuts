@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('report::admin.reports'))

    <li class="active">{{ trans('report::admin.reports') }}</li>
@endcomponent

@section('content')
    <div class="box box-primary report-wrapper">
        <h1>dfdfdfd</h1>
    </div>
@endsection
