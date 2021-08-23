<div class="row">
    <div class="col-md-8">
        {{ Form::file('file', trans('suppliers::attributes.logo'), $errors, $supplier, ['required' => true]) }}
        <div class="clearfix"></div>
        <div class="single-image image-holder-wrapper clearfix" style="padding: 10px;background: #f1f1f1;margin-bottom: 20px; margin-left:130px;display: inline-block;border-radius: 3px;vertical-align: bottom;">
            <div class="image-holder placeholder" style="position: relative;float: left;height: 125px;width: 125px;overflow: hidden;background: #fff;border: 1px solid #d2d6de;border-radius: 3px;cursor: move;z-index: 0;">
                @if($supplier)
                    <img src="{{$supplier->logo}}" style="    max-height: 100%;max-width: 100%;z-index: 1;position: absolute;top: 50%;left: 50%;-webkit-transform: translate(-50%,-50%);">
                @else
                    <i class="fa fa-picture-o" style="position: absolute;top: 50%;left: 50%;-webkit-transform: translate(-50%,-50%);transform: translate(-50%,-50%);font-size: 60px;color: #d9d9d9;z-index: -1;"></i>
                @endif
            </div>
        </div>
        <div class="media-picker-divider"></div>
        {{ Form::text('name', trans('suppliers::attributes.name'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('email', trans('suppliers::attributes.email'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('phone', trans('suppliers::attributes.phone'), $errors, $supplier, ['required' => true]) }}
        {{ Form::text('fax', trans('suppliers::attributes.fax'), $errors, $supplier, ['required' => false]) }}
        {{ Form::text('website', trans('suppliers::attributes.website'), $errors, $supplier, ['required' => false]) }}
        {{ Form::text('address', trans('suppliers::attributes.address'), $errors, $supplier, ['required' => true]) }}
    </div>
</div>
