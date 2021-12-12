<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('ngenius_enabled', trans('setting::attributes.ngenius_enabled'), trans('setting::settings.form.ngenius_enabled'), $errors, $settings) }}
        {{ Form::text('translatable[ngenius_label]', trans('setting::attributes.translatable.ngenius_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[ngenius_description]', trans('setting::attributes.translatable.ngenius_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}

    </div>
</div>
