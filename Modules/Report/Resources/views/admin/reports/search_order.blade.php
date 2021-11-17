@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('order::orders.search.advance_search'))

    <li><a href="{{ route('admin.orders.index') }}">{{ trans('order::orders.orders') }}</a></li>
    <li class="active">{{ trans('order::orders.search.advance_search') }}</li>
@endcomponent

@section('content')
    <div class="box box-primary">
        <div class="order-wrapper" style="padding: 20px">
            <h4 style="margin-bottom: 25px">{{ trans('order::orders.search.ticket') }}</h4>
            @if(isset($error))
                <h4 class="alert alert-danger">{{$error}}</h4>
            @endif
            <form method="POST" action="{{url("/en/admin/reports?type=ticket_report")}}">
                {{ csrf_field() }}

                <div class="form-group">
                    <div class="col-md-12" style="margin-bottom: 15px">
                        <input name="name" class="form-control " id="name" value="" labelcol="2" type="text" placeholder="Search by Ticket number">
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary btn-block" data-loading="">
                            Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
