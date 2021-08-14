@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('admin::resource.create', ['resource' => trans('votes::votes.votes')]))

    <li><a href="{{ route('admin.votes.index') }}">{{ trans('votes::votes.votes') }}</a></li>
    <li class="active">{{ trans('admin::resource.create', ['resource' => trans('votes::votes.votes')]) }}</li>
@endcomponent

@section('content')
    <form method="POST" action="{{ route('admin.votes.store') }}" class="form-horizontal" id="votes-create-form" novalidate>
        {{ csrf_field() }}

        {!! $tabs->render(compact('votes')) !!}
    </form>
@endsection

