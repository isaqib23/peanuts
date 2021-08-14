<?php

namespace Modules\Apis\Http\Controllers;

use Carbon\Carbon;
use Cartalyst\Sentinel\Checkpoints\NotActivatedException;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Darryldecode\Cart\CartCollection;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
use Modules\Account\Http\Requests\SaveAddressRequest;
use Modules\Address\Entities\Address;
use Modules\Address\Entities\DefaultAddress;
use Modules\Apis\Http\Requests\CheckoutRequest;
use Modules\Apis\Http\Requests\ProductRequest;
use Modules\Apis\Http\Requests\ProductsRequest;
use Modules\Apis\Http\Requests\SignupRequest;
use Modules\Cart\Facades\Cart;
use Modules\Cart\Http\Requests\StoreCartItemRequest;
use Modules\Checkout\Events\OrderPlaced;
use Modules\Checkout\Services\OrderService;
use Modules\Coupon\Checkers\MaximumSpend;
use Modules\Coupon\Checkers\MinimumSpend;
use Modules\Coupon\Exceptions\MaximumSpendException;
use Modules\Coupon\Exceptions\MinimumSpendException;
use Modules\Order\Entities\Order;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Page\Entities\Page;
use Modules\Payment\Facades\Gateway;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductLottery;
use Modules\Slider\Entities\Slider;
use Modules\Support\Country;
use Modules\User\Contracts\Authentication;
use Modules\User\Entities\Role;
use Modules\User\Entities\User;
use Modules\User\Events\CustomerRegistered;
use Modules\User\Http\Requests\LoginRequest;
use Modules\User\Http\Requests\RegisterRequest;
use Modules\User\Services\CustomerService;
use Modules\Votes\Entities\UserVote;
use Modules\Votes\Entities\Votes;

class ApisController extends Controller
{
    private $checkers = [
        MinimumSpend::class,
        MaximumSpend::class,
    ];

    /**
     * @var Authentication
     */
    private $auth;

    /**
     * ApisController constructor.
     * @param Authentication $auth
     */
    public function __construct(Authentication $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param \Modules\Apis\Http\Requests\LoginRequest $request
     * @return JsonResponse
     */
    public function login(\Modules\Apis\Http\Requests\LoginRequest $request){
        try {
            $loggedIn = $this->auth->login([
                'email' => $request->email,
                'password' => $request->password,
            ], (bool) $request->get('remember_me', false));

            if (! $loggedIn) {
                return response()->json([
                    'message' => trans('user::messages.users.invalid_credentials'),
                ],422);
            }

            return response()->json([
                'data' => $loggedIn
            ]);
        } catch (NotActivatedException $e) {
            return response()->json([
                'message' => trans('user::messages.users.account_not_activated'),
            ],422);
        } catch (ThrottlingException $e) {
            return response()->json([
                'message' => trans('user::messages.users.account_is_blocked'),
            ],422);
        }
    }

    /**
     * @param SignupRequest $request
     * @return JsonResponse
     */
    public function register(SignupRequest $request){
        $user = $this->auth->registerAndActivate($request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
        ]));

        $this->assignCustomerRole($user);

        event(new CustomerRegistered($user));

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * @param $user
     */
    protected function assignCustomerRole($user)
    {
        $role = Role::findOrNew(setting('customer_role'));

        if ($role->exists) {
            $this->auth->assignRole($user, $role);
        }
    }

    /**
     * @param ProductsRequest $request
     * @return JsonResponse
     */
    public function products(ProductsRequest $request){
        $status = ($request->input('status') == 'simple') ? 0 :1;
        $products = Product::filterByType($status);
        if($products->count() > 0){
            foreach ($products as $key => $value){
                if($value->product_type == 1){
                    $products[$key]->lottery = ProductLottery::where('product_id',$value->id)->first();
                }else{
                    $products[$key]->lottery = ProductLottery::where('link_product',$value->id)->first();
                }

                $products[$key]->sold_items = (string) getSoldLottery($value->id);
                $products[$key]->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $value->id);
            }
        }
        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * @param ProductRequest $request
     * @return JsonResponse
     */
    public function product(ProductRequest $request){
        if($request->input('status') == 'simple'){
            $lottery = ProductLottery::where('link_product',$request->input('id'))->first();
            $product = Product::getProductById($lottery->product_id);
            $product->lottery = $lottery;
        }else{
            $lottery = ProductLottery::where('product_id',$request->input('id'))->first();
            $product = Product::getProductById($lottery->link_product);
            $product->lottery = $lottery;
        }

        $product->sold_items = (string) getSoldLottery($product->id);
        $product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Modules\Apis\Http\Requests\StoreCartItemRequest $request
     * @return \Modules\Cart\Cart
     */
    public function addToCart(\Modules\Apis\Http\Requests\StoreCartItemRequest $request)
    {
        $getLottery = \Modules\Product\Entities\ProductLottery::where("product_id",$request->product_id)->first();
        if($getLottery && ($request->qty > (int)$getLottery->min_ticket)){
            return response()->json([
                'message' => "You can buy ".(int)$getLottery->min_ticket." items at once for this product",
            ],422);
        }

        Cart::store($request->product_id, $request->qty, $request->options ?? []);

        $user = User::where("id",$request->input('user_id'))->first();

        $options = $request->options ?? [];

        $userCart = \DB::table("user_cart")->where([
            "user_id"       => $request->input('user_id'),
            "product_id"    => $request->product_id
        ])->first();
        if(!is_null($userCart)){
            \DB::table("user_cart")->where([
                "user_id"       => $request->input('user_id'),
                "product_id"    => $request->product_id
            ])->update([
                "qty"   => (int) $request->qty + (int) $userCart->qty
            ]);
        }else {
            \DB::table("user_cart")->insert([
                "user_id" => $request->input('user_id'),
                "qty" => $request->qty,
                "product_id" => $request->product_id,
                "options" => json_encode($options),
            ]);
        }
        $user->wishlist()->detach($request->product_id);

        $cartArray = Cart::toArray();
        foreach ($cartArray as $key => $value){
            if($key == "items") {
                $cartArray["items"] = array_values($value->toArray());
            }
        }

        return $cartArray;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return \Modules\Cart\Cart
     */
    public function updateCart(Request $request)
    {
        $getLottery = \Modules\Product\Entities\ProductLottery::where("product_id",$request->product_id)->first();
        if($getLottery && ($request->qty > (int)$getLottery->min_ticket)){
            return response()->json([
                'message' => "You can buy ".(int)$getLottery->min_ticket." items at once for this product",
            ],422);
        }

        $cartArray = [];
        Cart::clear();
        $userCart = \DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $qty = $cart->qty;

                if($cart->product_id == $request->input('product_id')){
                    $qty = request('qty');

                    \DB::table("user_cart")->where([
                        "user_id"       => $request->input('user_id'),
                        "product_id"    => $request->product_id
                    ])->update([
                        "qty"   => $qty
                    ]);
                }

                Cart::store($cart->product_id, $qty, json_decode($cart->options) ?? []);
            }

            $cartArray = Cart::toArray();
            foreach ($cartArray as $key => $value){
                if($key == "items") {
                    $cartArray["items"] = array_values($value->toArray());
                }
            }
        }

        try {
            resolve(Pipeline::class)
                ->send(Cart::coupon())
                ->through($this->checkers)
                ->thenReturn();
        } catch (MinimumSpendException | MaximumSpendException $e) {
            Cart::removeCoupon();
        }

        return $cartArray;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return \Modules\Cart\Cart
     */
    public function destroyCart(Request $request)
    {
        Cart::clear();
        $userCart = \DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $qty = $cart->qty;
                if($cart->product_id != $request->input('product_id')){
                    Cart::store($cart->product_id, $qty, json_decode($cart->options) ?? []);
                }
            }
        }

        \DB::table("user_cart")->where([
            "user_id"       => $request->input('user_id'),
            "product_id"    => $request->input('product_id')
        ])->delete();

        return Cart::instance();
    }

    /**
     * @param Request $request
     * @return CartCollection
     */
    public function cart(Request $request)
    {
        Cart::clear();
        $userCart = \DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)){
            foreach ($userCart as $cart) {
                Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
            }

            $cartArray = Cart::toArray();
            foreach ($cartArray as $key => $value){
                if($key == "items") {
                    $cartArray["items"] = array_values($value->toArray());
                }
            }

            return $cartArray;
        }

        return [];
    }

    /**
     * @param CheckoutRequest $request
     * @param CustomerService $customerService
     * @param OrderService $orderService
     * @return JsonResponse
     */
    public function checkout(CheckoutRequest $request, CustomerService $customerService, OrderService $orderService)
    {
        Cart::clear();
        $userCart = \DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $qty = $cart->qty;
                Cart::store($cart->product_id, $qty, json_decode($cart->options) ?? []);
            }
        }


        if(Cart::items()->count() == 0) {
            return response()->json([
                'message' => "Cart is empty",
            ],422);
        }
        $order = $orderService->create($request);

        $gateway = Gateway::get($request->payment_method);

        try {
            $response = $gateway->purchase($order, $request);
        } catch (Exception $e) {
            $orderService->delete($order);

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        $orderId = json_encode($response);
        $orderId = json_decode($orderId, true);
        $orderId = $orderId["orderId"];

        $order = Order::findOrFail($orderId);

        $order->storeTransaction($response);

        event(new OrderPlaced($order));

        $order->update(['status' => "completed"]);

        updateProductLottery($orderId);

        $userCart = \DB::table("user_cart")->where("user_id", $request->input('user_id'))->delete();
        return response()->json($order);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function checkoutData(Request $request) {
        $user = User::where("id",$request->input('user_id'))->first();
        $data = [
            'countries' => Country::supported(),
            'gateways' => Gateway::all(),
            'defaultAddress' => $user->addresses ?? new DefaultAddress,
        ];

        return response()->json($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addToWishList(Request $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();

        if (! $user->wishlistHas(request('productId'))) {
            $user->wishlist()->attach(request('productId'));
        }

        $data = $user
            ->wishlist()
            ->with('files')
            ->latest()
            ->get();

        return response()->json($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyWishlistItem(Request $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();
        $productId = request('productId');

        $user->wishlist()->detach($productId);

        $data = $user
            ->wishlist()
            ->with('files')
            ->latest()
            ->get();

        return response()->json($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function wishlist(Request $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();
        $status = ($request->input('status') == 'simple') ? 0 :1;
        $data = $user
            ->wishlist()
            ->with('files')
            ->latest()
            ->get();

        $response = [];
        foreach ($data as $key => $value){
            if($value->product_type == 1){
                $data[$key]->lottery = ProductLottery::where('product_id',$value->id)->first();
            }else{
                $data[$key]->lottery = ProductLottery::where('link_product',$value->id)->first();
            }

            $data[$key]->product_type = (string) $value->product_type;
            $data[$key]->sold_items = (string) getSoldLottery($value->id);
            $data[$key]->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $value->id);


            if($value->product_type == $status){
                array_push($response,$value);
            }
        }
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orders(Request $request){
        $user = User::where("id",$request->input('user_id'))->first();
        $orders = $user
            ->orders()
            ->get();
        $response = [];

        foreach ($orders as $key => $order){
            if(count($order->products->pluck('product_id')->toArray()) > 0) {
                $products = (new \Modules\Product\Entities\Product)->getOrderLotteryProducts($order->products->pluck('product_id')->toArray());
                if($products->count() > 0) {
                    $products = $products->toArray();
                    foreach ($products as $proKey => $value){
                        if($request->status == 'active') {
                            $lottery = ProductLottery::where('product_id', $value["id"])
                                ->where('to_date', '>=', Carbon::now()->format('Y-m-d'))->first();
                        }else{
                            $lottery = ProductLottery::where('product_id', $value["id"])
                                ->where('to_date', '<', Carbon::now()->format('Y-m-d'))->first();
                        }
                        if(is_null($lottery)){
                            unset($products[$proKey]);
                        }else {
                            $products[$proKey]['lottery'] = $lottery;
                            $products[$proKey]['sold_items'] = (string)getSoldLottery($value["id"]);
                            $products[$proKey]['is_added_to_wishlist'] = isAddedToWishlist($request->input('user_id'), $value["id"]);
                        }
                    }
                    $products = array_values($products);
                    $products = array_reduce($products, 'array_merge', array());
                    if(count($products) > 0) {
                        array_push($response, $products);
                    }
                }
            }
        }
        return response()->json($response);
    }

    /**
     * @param SaveAddressRequest $request
     * @return JsonResponse
     */
    public function storeAddress(SaveAddressRequest $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();
        $address = $user->addresses()->create($request->all());

        return response()->json([
            "data" => $address
        ]);
    }

    /**
     * @param SaveAddressRequest $request
     * @return JsonResponse
     */
    public function updateAddress(SaveAddressRequest $request)
    {
        $address = Address::find($request->input('id'));
        $address->update($request->all());

        return response()->json([
            "data" => $address
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyAddress(Request $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();
        $user->addresses()->find($request->input('id'))->delete();

        return response()->json([
            'message' => trans('account::messages.address_deleted'),
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function slides(){
        $slides = (new Slider)->first()->slides->pluck('file');

        return response()->json([
            "data" => $slides
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function votes(){
        $votes = Votes::all();
        foreach ($votes as $key => $value){
            $product_1 = Product::findById($value->product_1);
            $product_1->vote_count = is_null($value->count_1) ? 0 : (int) $value->count_1;

            $product_2 = Product::findById($value->product_2);
            $product_2->vote_count = is_null($value->count_2) ? 0 : (int) $value->count_2;
            $votes[$key]->products = [$product_1,$product_2];

        }

        return response()->json([
            "data" => $votes
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function voteCast(Request $request){
        $vote_id        = $request->input('vote_id');
        $product_id     = $request->input('product_id');
        $user_id        = $request->input('user_id');
        $product_key    = $request->input('product_key');

        $getUserVote = UserVote::where([
            "user_id"       => $user_id,
            "vote_id"       => $vote_id,
            "product_id"    => $product_id
        ])->first();

        if(!$getUserVote){
            UserVote::insert([
                "user_id"       => $user_id,
                "vote_id"       => $vote_id,
                "product_id"    => $product_id
            ]);

            Votes::where([
                "id"                        => $vote_id,
                "product_".$product_key     => $product_id
            ])->increment('count_'.$product_key);
        }

        return response()->json([
            "message" => "Vote cast successfully!"
        ]);
    }
}
