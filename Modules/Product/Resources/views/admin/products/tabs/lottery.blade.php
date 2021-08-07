<div class="row">
    <div class="col-md-8">
        {{ Form::number('min_ticket', trans('product::attributes.min_ticket'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('max_ticket', trans('product::attributes.max_ticket'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('max_ticket_user', trans('product::attributes.max_ticket_user'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('winner', trans('product::attributes.winner'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('initial_price', trans('product::attributes.initial_price'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('bottom_price', trans('product::attributes.bottom_price'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('reduce_price', trans('product::attributes.reduce_price'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::number('current_price', trans('product::attributes.current_price'), $errors, $product, ['min' => 0, 'required' => true]) }}
        {{ Form::select('link_product', trans('product::attributes.link_product'), $errors, $link_product, $product) }}
        {{ Form::text('from_date', trans('product::attributes.from_date'), $errors, $product, ['class' => 'datetime-picker']) }}
        {{ Form::text('to_date', trans('product::attributes.to_date'), $errors, $product, ['class' => 'datetime-picker']) }}
    </div>
</div>
