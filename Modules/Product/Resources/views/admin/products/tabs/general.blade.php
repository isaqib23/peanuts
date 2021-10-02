{{ Form::text('name', trans('product::attributes.name'), $errors, $product, ['labelCol' => 2, 'required' => true]) }}
{{ Form::wysiwyg('description', trans('product::attributes.description'), $errors, $product, ['labelCol' => 2, 'required' => true]) }}

<div class="row">
    <div class="col-md-8">
        {{ Form::select('product_type', trans('product::attributes.product_type'), $errors, $types, $product) }}
        {{ Form::select('brand_id', trans('product::attributes.brand_id'), $errors, $brands, $product) }}
        {{ Form::select('supplier_id', trans('product::attributes.supplier_id'), $errors, $suppliers, $product) }}
        {{ Form::select('categories', trans('product::attributes.categories'), $errors, $categories, $product, ['class' => 'selectize prevent-creation', 'multiple' => true]) }}
        {{ Form::select('tax_class_id', trans('product::attributes.tax_class_id'), $errors, $taxClasses, $product) }}
        {{ Form::select('tags', trans('product::attributes.tags'), $errors, $tags, $product, ['class' => 'selectize prevent-creation', 'multiple' => true]) }}
        @if($product->product_type == 1 && $winners->count() > 1)
        {{ Form::select('winner_id', trans('product::attributes.winners'), $errors, $winners, $product) }}
        @endif
        {{ Form::checkbox('virtual', trans('product::attributes.virtual'), trans('product::products.form.the_product_won\'t_be_shipped'), $errors, $product) }}
        {{ Form::checkbox('is_active', trans('product::attributes.is_active'), trans('product::products.form.enable_the_product'), $errors, $product, ['checked' => true]) }}
    </div>
</div>
