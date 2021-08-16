<?php

namespace Modules\Admin\Ui;

use Illuminate\Contracts\Support\Responsable;
use Modules\Product\Entities\Product;

class AdminTable implements Responsable
{
    /**
     * Raw columns that will not be escaped.
     *
     * @var array
     */
    protected $rawColumns = [];

    /**
     * Raw columns that will not be escaped.
     *
     * @var array
     */
    protected $defaultRawColumns = [
        'checkbox', 'thumbnail', 'status', 'created',
    ];

    /**
     * Source of the table.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $source;

    /**
     * Create a new table instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $source
     * @return void
     */
    public function __construct($source = null)
    {
        $this->source = $source;
    }

    /**
     * Make table response for the resource.
     *
     * @param mixed $source
     * @return \Illuminate\Http\JsonResponse
     */
    public function make()
    {
        return $this->newTable();
    }

    /**
     * Create a new datatable instance;
     *
     * @param mixed $source
     * @return \Yajra\DataTables\DataTables
     */
    public function newTable()
    {
        $dataTable = datatables($this->source)
            ->addColumn('checkbox', function ($entity) {
                return view('admin::partials.table.checkbox', compact('entity'));
            })
            ->editColumn('status', function ($entity) {
                return $entity->is_active
                    ? '<span class="dot green"></span>'
                    : '<span class="dot red"></span>';
            })
            ->editColumn('created', function ($entity) {
                return view('admin::partials.table.date')->with('date', $entity->created_at);
            })
            ->rawColumns(array_merge($this->defaultRawColumns, $this->rawColumns))
            ->removeColumn('translations');


        if($this->source->getQuery()->from == "votes"){
            $dataTable->editColumn('product_1', function ($entity) {
                return (Product::findById($entity->product_1)) ? Product::findById($entity->product_1)->name : "-";
            })->editColumn('product_2', function ($entity) {
                return (Product::findById($entity->product_2)) ? Product::findById($entity->product_2)->name : "-";
            });
        }

        return $dataTable;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse($request)
    {
        return $this->make()->toJson();
    }
}
