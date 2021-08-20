<div class="row">
    <div class="col-md-8">
        @include('media::admin.image_picker.single', [
            'title' => trans('suppliers::suppliers.form.logo'),
            'inputName' => 'files[logo]',
            'file' => $supplier->logo,
        ])
        {{ Form::text('name', trans('suppliers::attributes.name'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('name', trans('suppliers::attributes.email'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('name', trans('suppliers::attributes.phone'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('name', trans('suppliers::attributes.fax'), $errors, $supplier, ['required' => false]) }}
        {{ Form::text('name', trans('suppliers::attributes.website'), $errors, $supplier, ['required' => false]) }}
        {{ Form::text('name', trans('suppliers::attributes.address'), $errors, $supplier, ['required' => true]) }}
    </div>
</div>
