<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('foloosi_enabled', trans('setting::attributes.foloosi_enabled'), trans('setting::settings.form.enable_foloosi'), $errors, $settings) }}
        {{ Form::text('translatable[foloosi_label]', trans('setting::attributes.translatable.foloosi_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[foloosi_description]', trans('setting::attributes.translatable.foloosi_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}

        <div class="{{ old('foloosi_enabled', array_get($settings, 'foloosi_enabled')) ? '' : 'hide' }}" id="foloosi-fields">
            {{ Form::text('foloosi_merchant_key', trans('setting::attributes.foloosi_merchant_key'), $errors, $settings, ['required' => true]) }}
            {{ Form::password('foloosi_secret_key', trans('setting::attributes.foloosi_secret_key'), $errors, $settings, ['required' => true]) }}
        </div>
    </div>
</div>
@push('scripts')
    <script type="text/javascript">
        $('#foloosi_enabled').on('change', () => {
            $('#foloosi-fields').toggleClass('hide');
        });
    </script>
@endpush
