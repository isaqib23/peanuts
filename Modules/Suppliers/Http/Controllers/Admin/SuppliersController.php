<?php

namespace Modules\Suppliers\Http\Controllers\Admin;

use Modules\Admin\Traits\HasCrudActions;
use Modules\Suppliers\Entities\Supplier;
use Modules\Suppliers\Http\Requests\CreateSupplierRequest;

class SuppliersController
{
    use HasCrudActions;

    /**
     * Model for the resource.
     *
     * @var string
     */
    protected $model = Supplier::class;

    /**
     * Label of the resource.
     *
     * @var string
     */
    protected $label = 'suppliers::suppliers.suppliers';

    /**
     * View path of the resource.
     *
     * @var string
     */
    protected $viewPath = 'suppliers::admin.suppliers';

    /**
     * Form requests for the resource.
     *
     * @var array
     */
    protected $validation = CreateSupplierRequest::class;
}
