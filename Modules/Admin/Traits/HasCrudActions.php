<?php

namespace Modules\Admin\Traits;

use FleetCart\OrderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Entities\File;
use Modules\Product\Entities\ProductLottery;
use Modules\Shipping\Facades\ShippingMethod;
use Modules\Support\Search\Searchable;
use Modules\Admin\Ui\Facades\TabManager;

trait HasCrudActions
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('query')) {
            return $this->getModel()
                ->search($request->get('query'))
                ->query()
                ->limit($request->get('limit', 10))
                ->get();
        }

        if ($request->has('table')) {
            return $this->getModel()->table($request);
        }

        return view("{$this->viewPath}.index");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = array_merge([
            'tabs' => TabManager::get($this->getModel()->getTable()),
            $this->getResourceName() => $this->getModel(),
        ], $this->getFormData('create'));

        return view("{$this->viewPath}.create", $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $this->disableSearchSyncing();

        if($this->getRequest('store')->route()->getName() == "admin.suppliers.store"){
            $data = $this->getRequest('store')->except('file');
            if($this->getRequest('store')->hasFile("file")) {
                $file = $this->getRequest('store')->file('file');
                $path = Storage::putFile('media', $file);

                $response = File::create([
                    'user_id' => auth()->id(),
                    'disk' => config('filesystems.default'),
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'extension' => $file->guessClientExtension() ?? '',
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);

                $data["logo"] = $response->path;
            }

            $entity = $this->getModel()->create(
                $data
            );
        }else {

            $entity = $this->getModel()->create(
                $this->getRequest('store')->all()
            );
        }

        $this->searchable($entity);

        $allowedRoutes = ["admin.products.update","admin.products.store"];
        if(in_array($this->getRequest('store')->route()->getName(), $allowedRoutes)){
            updateLotteryProduct($entity,$this->getRequest('store')->all(), "store");
            updateSimpleProduct($entity,$this->getRequest('store')->all());
        }

        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo($entity);
        }

        return redirect()->route("{$this->getRoutePrefix()}.index")
            ->withSuccess(trans('admin::messages.resource_saved', ['resource' => $this->getLabel()]));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $entity = $this->getEntity($id);
        if($this->getRoutePrefix() == "admin.orders"){
            $entity->tickets = OrderTicket::where(["order_id" => $entity->id])->get()->pluck("ticket_number");

            $shipping = \DB::table("user_shippings")->where(["order_id" => $entity->id])->first();
            $orderType = "";
            if($shipping){
                $getShipping = ShippingMethod::get($shipping->delivery_type);
                $orderType = $getShipping->label;
            }
            $entity->order_type = $orderType;
        }

        if (request()->wantsJson()) {
            return $entity;
        }

        return view("{$this->viewPath}.show")->with($this->getResourceName(), $entity);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data = array_merge([
            'tabs' => TabManager::get($this->getModel()->getTable()),
            $this->getResourceName() => $this->getEntity($id),
        ], $this->getFormData('edit', $id));

        $allowedRoutes = ["admin.products"];
        if(in_array($this->getRoutePrefix(), $allowedRoutes) && $data['product']->product_type == 1){
            $data = getLotteryProduct($id, $data);
            $getWinner = \DB::table('winners')->where("product_id",$data["product"]->product_id)->first();

            $data["product"]->winner_id = ($getWinner) ? $getWinner->winner_id : null;
        }

        return view("{$this->viewPath}.edit", $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        $entity = $this->getEntity($id);

        $this->disableSearchSyncing();

        if($this->getRequest('update')->route()->getName() == "admin.suppliers.update"){
            $data = $this->getRequest('update')->except('file');
            if($this->getRequest('update')->hasFile("file")) {
                $file = $this->getRequest('update')->file('file');
                $path = Storage::putFile('media', $file);

                $response = File::create([
                    'user_id' => auth()->id(),
                    'disk' => config('filesystems.default'),
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'extension' => $file->guessClientExtension() ?? '',
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);

                $data["logo"] = $response->path;
            }

            $entity->update(
                $data
            );
        }else {
            $entity->update(
                $this->getRequest('update')->all()
            );
        }

        $this->searchable($entity);

        $allowedRoutes = ["admin.products.update","admin.products.store"];
        if(in_array($this->getRequest('update')->route()->getName(), $allowedRoutes)){
            updateLotteryProduct($entity,$this->getRequest('store')->all(), 'update');
            //updateWinner($entity,$this->getRequest('store')->all());
            updateSimpleProduct($entity,$this->getRequest('store')->all());
        }

        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo($entity)
                ->withSuccess(trans('admin::messages.resource_saved', ['resource' => $this->getLabel()]));
        }

        return redirect()->route("{$this->getRoutePrefix()}.index")
            ->withSuccess(trans('admin::messages.resource_saved', ['resource' => $this->getLabel()]));
    }

    /**
     * Destroy resources by given ids.
     *
     * @param string $ids
     * @return void
     */
    public function destroy($ids)
    {
        $allowedRoutes = ["admin.products"];
        if(in_array($this->getRoutePrefix(), $allowedRoutes)){
            ProductLottery::whereIn('product_id', explode(',', $ids))->delete();
            ProductLottery::whereIn('link_product', explode(',', $ids))->delete();
            \DB::table("votes")->whereIn('product_1', explode(',', $ids))->delete();
            \DB::table("votes")->whereIn('product_2', explode(',', $ids))->delete();
            \DB::table("user_cart")->whereIn('product_id', explode(',', $ids))->delete();
        }
        //admin.products.destroy
        $this->getModel()
            ->withoutGlobalScope('active')
            ->whereIn('id', explode(',', $ids))
            ->delete();
    }

    /**
     * Get an entity by the given id.
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getEntity($id)
    {
        return $this->getModel()
            ->with($this->relations())
            ->withoutGlobalScope('active')
            ->findOrFail($id);
    }

    /**
     * Get the relations that should be eager loaded.
     *
     * @return array
     */
    private function relations()
    {
        return collect($this->with ?? [])->mapWithKeys(function ($relation) {
            return [$relation => function ($query) {
                return $query->withoutGlobalScope('active');
            }];
        })->all();
    }

    /**
     * Get form data for the given action.
     *
     * @param string $action
     * @param mixed ...$args
     * @return array
     */
    protected function getFormData($action, ...$args)
    {
        if (method_exists($this, 'formData')) {
            return $this->formData(...$args);
        }

        if ($action === 'create' && method_exists($this, 'createFormData')) {
            return $this->createFormData();
        }

        if ($action === 'edit' && method_exists($this, 'editFormData')) {
            return $this->editFormData(...$args);
        }

        return [];
    }

    /**
     * Get name of the resource.
     *
     * @return string
     */
    protected function getResourceName()
    {
        if (isset($this->resourceName)) {
            return $this->resourceName;
        }

        return lcfirst(class_basename($this->model));
    }

    /**
     * Get label of the resource.
     *
     * @return void
     */
    protected function getLabel()
    {
        return trans($this->label);
    }

    /**
     * Get route prefix of the resource.
     *
     * @return string
     */
    protected function getRoutePrefix()
    {
        if (isset($this->routePrefix)) {
            return $this->routePrefix;
        }

        return "admin.{$this->getModel()->getTable()}";
    }

    /**
     * Get a new instance of the model.
     *
     * @return \Modules\Support\Eloquent\Model
     */
    protected function getModel()
    {
        return new $this->model;
    }

    /**
     * Get request object
     *
     * @param string $action
     * @return \Illuminate\Http\Request
     */
    protected function getRequest($action)
    {
        if (! isset($this->validation)) {
            return request();
        }

        if (isset($this->validation[$action])) {
            return resolve($this->validation[$action]);
        }

        return resolve($this->validation);
    }

    /**
     * Disable search syncing for the entity.
     *
     * @return void
     */
    protected function disableSearchSyncing()
    {
        if ($this->isSearchable()) {
            $this->getModel()->disableSearchSyncing();
        }
    }

    /**
     * Determine if the entity is searchable.
     *
     * @return bool
     */
    protected function isSearchable()
    {
        return in_array(Searchable::class, class_uses_recursive($this->getModel()));
    }

    /**
     * Make the given model instance searchable.
     *
     * @return void
     */
    protected function searchable($entity)
    {
        if ($this->isSearchable($entity)) {
            $entity->searchable();
        }
    }
}
