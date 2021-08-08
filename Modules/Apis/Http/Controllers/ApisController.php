<?php

namespace Modules\Apis\Http\Controllers;

use Cartalyst\Sentinel\Checkpoints\NotActivatedException;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Darryldecode\Cart\CartCollection;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
use Modules\Address\Entities\DefaultAddress;
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
use Modules\Support\Country;
use Modules\User\Contracts\Authentication;
use Modules\User\Entities\Role;
use Modules\User\Entities\User;
use Modules\User\Events\CustomerRegistered;
use Modules\User\Http\Requests\LoginRequest;
use Modules\User\Http\Requests\RegisterRequest;
use Modules\User\Services\CustomerService;

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
            $product = Product::getProductById($request->input('id'));
            $product->lottery = ProductLottery::where('product_id',$request->input('id'))->first();
        }else{
            $product = Product::getProductById($request->input('id'));
            $product->lottery = ProductLottery::where('link_product',$request->input('id'))->first();
        }
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
        Cart::store($request->product_id, $request->qty, $request->options ?? []);

        return Cart::instance();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return \Modules\Cart\Cart
     */
    public function updateCart(Request $request)
    {
        $cartItemId = $request->input('cart_id');
        Cart::updateQuantity($cartItemId, request('qty'));

        try {
            resolve(Pipeline::class)
                ->send(Cart::coupon())
                ->through($this->checkers)
                ->thenReturn();
        } catch (MinimumSpendException | MaximumSpendException $e) {
            Cart::removeCoupon();
        }

        return Cart::instance();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return \Modules\Cart\Cart
     */
    public function destroyCart(Request $request)
    {
        $cartItemId = $request->input('cart_id');
        Cart::remove($cartItemId);

        return Cart::instance();
    }

    /**
     * @param Request $request
     * @return CartCollection
     */
    public function cart(Request $request)
    {
        if(Cart::items()->count() > 0){
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
     * @param StoreOrderRequest $request
     * @param CustomerService $customerService
     * @param OrderService $orderService
     * @return JsonResponse
     */
    public function checkout(StoreOrderRequest $request, CustomerService $customerService, OrderService $orderService)
    {
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


        return response()->json($order);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function checkoutData(Request $request) {
        $user = User::where($request->input('user_id'))->first();
        dd($user);
        $data = [
            'countries' => Country::supported(),
            'gateways' => Gateway::all(),
            'defaultAddress' => auth()->user()->addresses ?? new DefaultAddress,
        ];

        return response()->json($data);
    }
}
