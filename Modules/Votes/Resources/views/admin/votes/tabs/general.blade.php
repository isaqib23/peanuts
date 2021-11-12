<div class="row">
    <div class="col-md-8">
        {{ Form::select('product_1', trans('votes::attributes.product_1'), $errors, $product_1, $votes, ['required' => true]) }}
        {{ Form::select('product_2', trans('votes::attributes.product_2'), $errors, $product_2, $votes, ['required' => true]) }}
        {{ Form::select('status', trans('votes::attributes.status'), $errors, $status, $votes, ['required' => true]) }}
    </div>
</div>
