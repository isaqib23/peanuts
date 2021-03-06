<?php

namespace Modules\Apis\Http\Controllers;

use Carbon\Carbon;
use Cartalyst\Sentinel\Checkpoints\NotActivatedException;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Darryldecode\Cart\CartCollection;
use DB;
use FleetCart\Mail\VerificationEmail;
use FleetCart\OrderTicket;
use FleetCart\UserCoupon;
use GuzzleHttp\Client;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Account\Http\Requests\SaveAddressRequest;
use Modules\Address\Entities\Address;
use Modules\Address\Entities\DefaultAddress;
use Modules\Apis\Http\Requests\ChangePasswordRequest;
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
use Modules\Coupon\Entities\Coupon;
use Modules\Coupon\Exceptions\MaximumSpendException;
use Modules\Coupon\Exceptions\MinimumSpendException;
use Modules\Media\Entities\File;
use Modules\Order\Entities\Order;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Page\Entities\Page;
use Modules\Payment\Facades\Gateway;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductLottery;
use Modules\Product\Entities\ProductTranslation;
use Modules\Setting\Entities\Setting;
use Modules\Shipping\Facades\ShippingMethod;
use Modules\Slider\Entities\Slider;
use Modules\Support\Country;
use Modules\Support\Locale;
use Modules\Support\Money;
use Modules\Support\State;
use Modules\User\Contracts\Authentication;
use Modules\User\Entities\Role;
use Modules\User\Entities\User;
use Modules\User\Events\CustomerRegistered;
use Modules\User\Http\Requests\LoginRequest;
use Modules\User\Http\Requests\PasswordResetRequest;
use Modules\User\Http\Requests\RegisterRequest;
use Modules\User\Mail\ResetPasswordEmail;
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
            'password'
        ]));

        $users = User::where("id",$user->id)->first();

        $this->assignCustomerRole($user);

        if($request->input('image')) {
            $file = $request->input('image');

            $image = str_replace('data:image/png;base64,', '', $file);
            $image = str_replace(' ', '+', $image);
            $imageName = str_random(10).'.'.'png';
            \File::put(public_path(). '/storage/media/' . $imageName, base64_decode($image));

            $response = File::create([
                'user_id' => $user->id,
                'disk' => config('filesystems.default'),
                'filename' => $imageName,
                'path' => 'media/'.$imageName,
                'extension' => 'png',
                'mime' => 'image/png',
                'size' => 132,
            ]);

            $request->merge(["photo" => $response->path]);

            $users->update($request->all());
        }

        event(new CustomerRegistered($user));


        /*$code = $this->auth->createActivation($users);
        $email = $request->email;

        $maildata = [
            'title' => 'Hi '.$request->first_name,
            'message_body' => 'Please click on below button to verify your email address on Pnutso',
            'link' => url('/email_confirmation?code='.$user->id.'_'.$code),
        ];

        Mail::to($email)->send(new VerificationEmail($maildata));

        return response()->json([
            'message' => trans('user::messages.users.account_created'),
            "user" => $users
        ],200);
        */

        return response()->json([
            'data' => $user
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

        $response = [];
        if($products->count() > 0){
            foreach ($products as $key => $value){
                if($value->product_type == 1){
                    $products[$key]->lottery = ProductLottery::where('product_id',$value->id)->first();
                }else{
                    $products[$key]->lottery = ProductLottery::where('link_product',$value->id)->first();
                }

                $getCart = DB::table("user_cart")->where([
                    "user_id"       => $request->input('user_id'),
                    "product_id"    => $value->id
                ])->first();

                $products[$key]->cart_qty = ($getCart) ? $getCart->qty : 0;
                $products[$key]->sold_items = (string) getSoldLottery($value->id);
                $products[$key]->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $value->id);
                $products[$key]->thumbnail_image = (!is_null($value->base_image->path)) ? $value->base_image : NULL;
                $products[$key]->suppliers = ($value->supplier->count() > 1) ? $value->supplier : NULL;
                $products[$key]->sold_tickets = OrderTicket::where(["product_id" => $value->id, "status" => "sold"])->count();
                $products[$key]->total_tickets = OrderTicket::where(["product_id" => $value->id])->count();
                $products[$key]->is_out_of_stock = (getRemainingTicketsCount($value->id) > 0) ? false : true;
                $products[$key]->is_expired = checkLotteryExpiry($value,$value->lottery);


                if($products[$key]->lottery){
                    array_push($response,$products[$key]);
                }
            }
        }
        return response()->json([
            'data' => $response,
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

        $product->sold_tickets = OrderTicket::where(["product_id" => $product->id, "status" => "sold"])->count();
        $product->total_tickets = OrderTicket::where(["product_id" => $product->id])->count();
        $product->is_out_of_stock = (getRemainingTicketsCount($product->id) > 0) ? false : true;
        $product->is_expired = checkLotteryExpiry($product,$product->lottery);

        $getCart = DB::table("user_cart")->where([
            "user_id"       => $request->input('user_id'),
            "product_id"    => $product->id
        ])->first();

        $product->cart_qty = ($getCart) ? $getCart->qty : 0;
        $product->sold_items = (string) getSoldLottery($product->id);
        $product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
        $product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
        $product->suppliers = ($product->supplier->count() > 1) ? $product->supplier : NULL;
        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Modules\Apis\Http\Requests\StoreCartItemRequest $request
     * @return array
     */
    public function addToCart(\Modules\Apis\Http\Requests\StoreCartItemRequest $request)
    {
        $getLottery = ProductLottery::where("product_id",$request->product_id)->first();
        $soldTickets = getSoldLottery($request->product_id);
        $getProduct = Product::where("id",$request->product_id)->first();
        /*if($getLottery && ($request->qty > (int)$getLottery->min_ticket)){
            return response()->json([
                'message' => "You can buy ".(int)$getLottery->min_ticket." items at once for this product",
            ],422);
        }*/
        if($getLottery){
            if($request->qty > (int)$getLottery->max_ticket_user){
                return response()->json([
                    'message' => "You can buy ".(int)$getLottery->max_ticket_user." items at once for this product",
                ],422);
            }

            $remainingTickets = getRemainingTicketsCount($request->product_id);
            if($remainingTickets <= 0) {
                return response()->json([
                    'message' => "All tickets are already sold",
                ],422);
            }
            if($request->qty > $remainingTickets) {
                return response()->json([
                    'message' => "You can buy ".$remainingTickets." items for this product",
                ],422);
            }
        }

        Cart::store($request->product_id, $request->qty, $request->options ?? []);

        $user = User::where("id",$request->input('user_id'))->first();

        $options = $request->options ?? [];

        $userCart = DB::table("user_cart")->where([
            "user_id"       => $request->input('user_id'),
            "product_id"    => $request->product_id
        ])->first();
        if(!is_null($userCart)){
            if($getProduct->product_type == 0){
                $qty = 1;
            }else{
                $qty = (int) $request->qty + (int) $userCart->qty;
            }
            DB::table("user_cart")->where([
                "user_id"       => $request->input('user_id'),
                "product_id"    => $request->product_id
            ])->update([
                "qty"   => $qty,
                "product_type" => $getProduct->product_type,
            ]);
        }else {
            DB::table("user_cart")->insert([
                "user_id" => $request->input('user_id'),
                "qty" => $request->qty,
                "product_id" => $request->product_id,
                "product_type" => $getProduct->product_type,
                "options" => json_encode($options),
            ]);
        }
        //$user->wishlist()->detach($request->product_id);

        $cartArray = Cart::toArray();
        foreach ($cartArray as $key => $value){
            if($key == "items") {
                $cartArray["items"] = array_values($value->toArray());
            }
        }

        return getUserCart($request);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return \Modules\Cart\Cart
     */
    public function updateCart(Request $request)
    {
        $getLottery = ProductLottery::where("product_id",$request->product_id)->first();
        /*if($getLottery && ($request->qty > (int)$getLottery->min_ticket)){
            return response()->json([
                'message' => "You can buy ".(int)$getLottery->min_ticket." items at once for this product",
            ],422);
        }*/

        if($getLottery && ($request->qty > (int)$getLottery->max_ticket_user)){
            return response()->json([
                'message' => "You can buy ".(int)$getLottery->max_ticket_user." items at once for this product",
            ],422);
        }

        $cartArray = [];
        Cart::clear();
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $qty = $cart->qty;

                if($cart->product_id == $request->input('product_id')){
                    $qty = request('qty');

                    DB::table("user_cart")->where([
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
foreach ($cartArray["items"] as $key1 => $value1){
                    $product = $value1->product;
                    $cartArray["items"][$key1]->product->sold_items = (string) getSoldLottery($product->id);
                    $cartArray["items"][$key1]->product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
                    $cartArray["items"][$key1]->product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
                    $cartArray["items"][$key1]->product->suppliers = ($product->supplier->count() > 1) ? $product->supplier : NULL;
                    $cartArray["items"][$key1]->qty = (string)$value1->qty;
                }
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
        DB::table("user_cart")->where([
            "user_id"       => $request->input('user_id'),
            "product_id"    => $request->input('product_id')
        ])->delete();

        Cart::clear();
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $getProduct = Product::getProductById($cart->product_id);
                if($getProduct && $getProduct->product_type == 1) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }
            }

            $cartArray = Cart::toArray();
            foreach ($cartArray as $key => $value) {
                if ($key == "items") {
                    $cartArray["items"] = array_values($value->toArray());
                    foreach ($cartArray["items"] as $key1 => $value1) {
                        $product = $value1->product;
                        $cartArray["items"][$key1]->product->sold_items = (string)getSoldLottery($product->id);
                        $cartArray["items"][$key1]->product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
                        $cartArray["items"][$key1]->product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
                        $cartArray["items"][$key1]->product->suppliers = ($product->supplier->count() > 1) ? $product->supplier : NULL;
$cartArray["items"][$key1]->qty = (string)$value1->qty;
                    }
                }
            }

            return $cartArray;
        }

        return [];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function cart(Request $request)
    {
        return getUserCart($request);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function directCart(Request $request)
    {
        return getdirectCart($request);
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
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $getProduct = Product::getProductById($cart->product_id);
                if($request->input('type') == "direct" && $getProduct && $getProduct->product_type == 0) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }

                if($request->input('type') == "lottery" && $getProduct && $getProduct->product_type == 1) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }
            }
        }


        if(Cart::items()->count() == 0) {
            return response()->json([
                'message' => "Cart is empty",
            ],422);
        }

        $order = $orderService->create($request);

        /*$gateway = Gateway::get($request->payment_method);

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
        $orderId = $orderId["orderId"];*/
        $orderId = $order->id;

        event(new OrderPlaced($order));

        updateProductLottery($orderId);

        $order = Order::findOrFail($orderId);

        $order->storeFoloosiTransaction($request->input('transaction_id'));

        $order->update(['status' => "completed"]);

        DB::table("user_cart")->where("user_id", $request->input('user_id'))->delete();

        return response()->json($order);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function checkoutData(Request $request) {
        $user = User::where("id",$request->input('user_id'))->first();
        $countries = [];
        foreach (Country::supported() as $key => $value){
            $data = ["id" => $key, "name" => $value];
            $data["states"] = [];
            if(State::get($key)) {
                foreach (State::get($key) as $key1 => $value1) {
                    array_push($data["states"], ["id" => $key1, "name" => $value1]);
                }
            }
            array_push($countries,$data);
        }
        $gateways = [];
        foreach (Gateway::all() as $key => $value){
            $value->id = $key;
            array_push($gateways,$value);
        }

        $shippingMethods = [];
        foreach (ShippingMethod::all() as $key => $value){
            array_push($shippingMethods,[
                "name"  => $value->name,
                "label" => $value->label,
                "cost" => (string) $value->cost->amount()
            ]);
        }

        $data = [
            'countries' => $countries,
            'gateways' => $gateways,
            'shipping' => $shippingMethods,
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
            $data[$key]->thumbnail_image = (!is_null($value->base_image->path)) ? $value->base_image : NULL;
            $data[$key]->suppliers = ($value->supplier->count() > 1) ? $value->supplier : NULL;
            $data[$key]->sold_tickets = OrderTicket::where(["product_id" => $value->id, "status" => "sold"])->count();
            $data[$key]->total_tickets = OrderTicket::where(["product_id" => $value->id])->count();
            $data[$key]->is_out_of_stock = (getRemainingTicketsCount($value->id) > 0) ? false : true;
            $data[$key]->is_expired = checkLotteryExpiry($value,$value->lottery);

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
                $products = (new Product)->getOrderLotteryProducts($order->products->pluck('product_id')->toArray());
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
                            $products[$proKey]['lottery']->name = ProductTranslation::where(["product_id" => $lottery->product_id])->first()->name;
                            $products[$proKey]['tickets'] = getSoldTickets($value["id"],$order->id);
                            $products[$proKey]['is_added_to_wishlist'] = isAddedToWishlist($request->input('user_id'), $value["id"]);
                            $products[$proKey]['thumbnail_image'] = (count($value["base_image"]) > 0) ? $value["base_image"] : NULL;
                            $products[$proKey]['suppliers'] = (count($value["supplier"]) > 0) ? $value["supplier"] : NULL;

                            $products[$proKey]['sold_tickets'] = OrderTicket::where(["product_id" => $value["id"], "status" => "sold"])->count();
                            $products[$proKey]['total_tickets'] = OrderTicket::where(["product_id" => $value["id"]])->count();
                            $products[$key]['is_out_of_stock'] = (getRemainingTicketsCount($value["id"]) > 0) ? false : true;
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
        $slides = (new Slider)->orderby("id","desc")->first()->slides->pluck('file');

        return response()->json([
            "data" => $slides
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function votes(Request $request){
        $votes = Votes::where("status","0")->get();
        foreach ($votes as $key => $value){
            $product_1 = Product::findById($value->product_1);
            $product_1->vote_count = is_null($value->count_1) ? 0 : (int) $value->count_1;

            $product_2 = Product::findById($value->product_2);
            $product_2->vote_count = is_null($value->count_2) ? 0 : (int) $value->count_2;

            $totalVotes = $product_1->vote_count + $product_2->vote_count;
            if($totalVotes > 0) {
                $product_2->vote_percentage = (string) ($product_2->vote_count / $totalVotes) * 100;
                $product_1->vote_percentage = (string) ($product_1->vote_count / $totalVotes) * 100;
            }else{
                $product_2->vote_percentage = "0";
                $product_1->vote_percentage = "0";
            }

            $product_1->thumbnail_image = (!is_null($product_1->base_image->path)) ? $product_1->base_image : NULL;
            $product_1->suppliers = (!is_null($product_1->supplier->id)) ? $product_1->base_image : NULL;

            $product_2->thumbnail_image = (!is_null($product_2->base_image->path)) ? $product_2->base_image : NULL;
            $product_2->suppliers = (!is_null($product_2->supplier->id)) ? $product_2->base_image : NULL;

            $votes[$key]->products = [$product_1,$product_2];

            $votes[$key]->vote_casted = (boolean) UserVote::where(["vote_id" => $value->id, "user_id" => $request->input("user_id")])->first();
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
            "vote_id"       => $vote_id
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

    /**
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = User::where("id",$request->input('user_id'))->first();

        $check = Hash::check($request->input('current_password'), $user->password);
        if(!$check){
            return response()->json([
                "message" => trans('account::messages.current_password')
            ]);
        }

        $request->bcryptPassword($request);

        $user->update($request->except('user_id'));

        return response()->json([
            "message" => trans('account::messages.profile_updated')
        ]);
    }

    /**
     * @param PasswordResetRequest $request
     * @return JsonResponse
     */
    public function postReset(PasswordResetRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (is_null($user)) {
            return response()->json([
                "message" => trans('user::messages.users.no_user_found')
            ]);
        }

        $code = $this->auth->createReminderCode($user);

        Mail::to($user)
            ->send(new ResetPasswordEmail($user, $this->resetCompleteRoute($user, $code)));

        return response()->json([
            "message" => trans('user::messages.users.check_email_to_reset_password')
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function completeOrder(Request $request){
        $orderId = $request->input('order_id');

        $order = Order::findOrFail($orderId);

        $order->storeFoloosiTransaction($request->input('transaction_id'));

        $order->update(['status' => "completed"]);

        return response()->json([
            "message" => "Order Completed"
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateProfile(Request $request){
        $user = User::where("id",$request->input('user_id'))->first();

        if($request->input('image')) {
            $file = $request->input('image');

            $image = str_replace('data:image/png;base64,', '', $file);
            $image = str_replace(' ', '+', $image);
            $imageName = str_random(10).'.'.'png';
            \File::put(public_path(). '/storage/media/' . $imageName, base64_decode($image));

            $response = File::create([
                'user_id' => $user->id,
                'disk' => config('filesystems.default'),
                'filename' => $imageName,
                'path' => 'media/'.$imageName,
                'extension' => 'png',
                'mime' => 'image/png',
                'size' => 132,
            ]);

            $request->merge(["photo" => $response->path]);
        }

        $user->update($request->all());

        return response()->json([
            "data" => $user
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function clearCart(Request $request)
    {
        Cart::clear();
        DB::table("user_cart")->where("user_id", $request->input('user_id'))->delete();

        return response()->json([
            "data" => []
        ]);
    }

    public function getCartQty(Request $request){
        Cart::clear();
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)) {
            foreach ($userCart as $cart) {
                $getProduct = Product::getProductById($cart->product_id);
                if ($getProduct && $getProduct->product_type == 1) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }
            }
        }

        return response()->json([
            "data" => Cart::quantity()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getWinners(Request $request){
        $getWinners = \DB::table('winners')->get();
        if($getWinners){
            $users = (new User())->getWinners();
            $response = [];
            foreach ($users as $key => $value){
                $product = Product::getProductById($value->product_id);
                if($product) {
                    $value->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
                    $value->order_id = md5($value->order_id);
                    $value->created_at = date('Y-m-d',strtotime($value->created_at));
                    array_push($response, $value);
                }
            }

            return response()->json([
                "data" => $response
            ]);
        }

        return response()->json([
            "data" => []
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function peanutProducts(Request $request){
        $products = (new Product())->getPeanutProducts();

        if($products->count() > 0){
            foreach ($products as $key => $value){
                $products[$key]->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $value->id);
                $products[$key]->thumbnail_image = (!is_null($value->base_image->path)) ? $value->base_image : NULL;
foreach ($products[$key]->translations as $key1=>$tra){
                    $products[$key]->translations[$key1]->product_id = (string)$tra->product_id;
                }
            }
        }
        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * @param Request $request
     * @param OrderService $orderService
     * @return JsonResponse
     */
    public function createPayment(Request $request, OrderService $orderService){
        if($request->input("is_direct") == "true") {
            getdirectCart($request);
        }else {
            getUserCart($request);
        }

        $user = User::where("id",$request->input('user_id'))->first();
        $userAddress = Address::where("id",$request->input('address'))->first();

        if(!is_null($request->input('address'))) {
            $request->merge([
                "customer_email" => $user->email,
                "customer_phone" => $user->phone,
                "billing" => $userAddress->toArray(),
                "billingAddressId" => $userAddress->id,
                "shippingAddressId" => $userAddress->id,
                "newBillingAddress" => false,
                "newShippingAddress" => false,
                "payment_method" => "network"
            ]);

            $airway_bill = generateAirWayBill($request->input('user_id'), $request->input('address'));
        }else{
            $request->merge([
                "customer_email" => $user->email,
                "customer_phone" => $user->phone,
                "billing" => null,
                "billingAddressId" => null,
                "shippingAddressId" => null,
                "newBillingAddress" => false,
                "newShippingAddress" => false,
                "payment_method" => "network"
            ]);
            $airway_bill = 0;
        }

        $order = $orderService->create($request);
        $order->update(['airway_bill' => $airway_bill]);

        DB::table("user_shippings")->where([
            "user_id"       => $request->input('user_id'),
            "address_id"    => $request->input("address")
            ])->whereNull("order_id")->update(["order_id" => $order->id]);

        $gateway = Gateway::get("ngenius");

        try {
            $response = $gateway->purchase($order, $request);
        } catch (Exception $e) {
            $orderService->delete($order);

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        if($request->input("is_direct") == "true") {
            DB::table("user_cart")->where(["user_id" => $request->input('user_id'), "product_type" => 0])->delete();
        }else {
            DB::table("user_cart")->where(["user_id" => $request->input('user_id'), "product_type" => 1])->delete();
        }

        DB::table("users_coupons")->where("user_id", $request->input('user_id'))->delete();
        return response()->json([
            'data' => [
                "order_reference"       => $response["order_reference"],
                "order_payment_url"     => $response["payment_page_url"]."&slim=true",
            ],
        ]);
    }

    public function order_confirmation1(Request $request, OrderService $orderService){
        $orderRef = $request->input('ref');

        $apikey = env('NETWORK_API_KEY');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "accept: application/vnd.ni-identity.v1+json",
            "authorization: Basic ".$apikey,
            "content-type: application/vnd.ni-identity.v1+json"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  "{\"realmName\":\"ni\"}");
        $output = json_decode(curl_exec($ch));
        $access_token = $output->access_token;
        curl_close ($ch);

        $outlet = env('NETWORK_OUTLET');
        $token = $access_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/".$outlet."/orders/".$orderRef);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/vnd.ni-payment.v2+json",
            "Accept: application/vnd.ni-payment.v2+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = json_decode(curl_exec($ch));

        $callBackData = $output->merchantAttributes->merchantOrderReference;
        $user_id = strtok($callBackData, '-');
        $orderId = substr($callBackData, strpos($callBackData, "-") + 1);

        updateProductLottery($orderId);
        updateProductTickets($orderId);

        $order = Order::findOrFail($orderId);

        $order->storeFoloosiTransaction($request->input('ref'));

        $order->update(['status' => "completed"]);

        curl_close ($ch);

        return redirect('/home');exit;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function get_delivery_rates(Request $request){
        getUserCart($request);

        $userShipping = ShippingMethod::get($request->input('delivery_type'));
        if(is_null($userShipping)){
            return response()->json([
                "message" => "Invalid Shipping",
            ],422);
        }

        $user_id = $request->input('user_id');
        $address_id = $request->input('address_id');
        saveUserShipping($request, $userShipping);

        if ($address_id) {
            $userAddress = Address::where("id", $address_id)->first();
            if ($userAddress->country == "AE") {
                $userShipping->content = "";
                saveUserShipping($request, $userShipping);
                Cart::addShippingMethod($userShipping);

                return response()->json([
                    'data' => [
                        "carrier_name" => null,
                        "delivery_rate" => (string) $userShipping->cost->amount(),
                        "delivery_in_days" => null,
                        "cart_total" => Cart::total()->currency() . " " . number_format((float)Cart::total()->amount(), 2, '.', '')
                    ],
                ]);
            }

            $user = User::where("id", $user_id)->first();

            try {
                $dhlRate = getDHLDeliveryRate($user, $userAddress);
                $userShipping->cost = Money::inDefaultCurrency($dhlRate["delivery_rate"]);
                saveUserShipping($request, $userShipping);
                Cart::addShippingMethod($userShipping);

                $dhlRate['cart_total'] = Cart::total()->currency() . " " . number_format((float)Cart::total()->amount(), 2, '.', '');
                return response()->json([
                    'data' => $dhlRate,
                ]);
            } catch (\Exception $e) {
                Cart::removeShippingMethod();
                \DB::table("user_shippings")->where("user_id",$request->user_id)
                    ->update([
                        "address_id"        => null,
                        "content"    => null,
                        "label"         => null,
                        "amount"        => null,
                        "updated_at"    => date("Y-m-d H:i:s")
                    ]);
                return response()->json([
                    'message' => "Delivery address is not valid",
                ], 422);
            }
        }else{
            return response()->json([
                'data' => [
                    "carrier_name" => null,
                    "delivery_rate" => null,
                    "delivery_in_days" => null,
                    "cart_total" => Cart::total()->currency() . " " . number_format((float)Cart::total()->amount(), 2, '.', '')
                ],
            ]);
        }
    }

    protected function resetCompleteRoute($user, $code)
    {
        return route('reset.complete', [$user->email, $code]);
    }

    public function activateUser(Request $request){
        $this->auth->activate($request->user_id, $request->code);
        $users = User::where("id",$request->user_id)->first();

        return response()->json([
            "message" => trans('account::messages.active_account'),
            "data"  => $users
        ]);
    }

    public function order_confirmation(Request $request, OrderService $orderService){
        $json = file_get_contents("php://input");
        \DB::table("webhook")->insert([
            "content" => $json,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        ]);
        $output = json_decode($json);

        if ($output->eventName == "PURCHASED") {
            $callBackData = $output->order->merchantAttributes->merchantOrderReference;
            $user_id = strtok($callBackData, '-');
            $orderId = substr($callBackData, strpos($callBackData, "-") + 1);

            $order = Order::findOrFail($orderId);

            $order->storeFoloosiTransaction($output->order->reference);

            $order->update(['status' => "completed"]);

            updateProductLottery($orderId);
            updateProductTickets($orderId);
        }
    }

    public function apply_coupon(Request $request){
        $coupon = Coupon::where("code",$request->code)->first();
        if($coupon) {
            $userCoupon = UserCoupon::firstOrNew([
                'user_id' => $request->input("user_id"),
                'coupon_code' => $request->input("code")
            ]);
            $userCoupon->save();

            return response()->json([
                'data' => getUserCart($request)
            ]);
        }else{
            return response()->json([
                'data' => false
            ]);
        }
    }

    public function email_confirmation(Request $request){
        $code = $request->code;
        $user_id = strtok($code, '_');
        $confirmationCode = substr($code, strpos($code, "_") + 1);

        $this->auth->activate($user_id, $confirmationCode);

        return response()->json([
            "message" => trans('account::messages.active_account')
        ]);
    }

    public function remove_coupon(Request $request){
        $coupon = Coupon::where("code",$request->code)->first();
        if($coupon) {
            UserCoupon::where([
                'user_id' => $request->input("user_id")
            ])->delete();

            return response()->json([
                'data' => getUserCart($request)
            ]);
        }else{
            return response()->json([
                'data' => false
            ]);
        }
    }

    public function getStaticPages(Request $request){

        $locale = $request->input("lang");
        $languages = array_keys(Locale::supported());

        if(!in_array($locale,$languages)){
            return response()->json([
                'message' => "Language not supported"
            ],422);
        }

        config(['app.locale' => $locale]);
        $pages = Page::all();

        $response = [];
        foreach ($pages as $page){
            if($locale == "en"){
                $trans = $page->translations["0"];
            }else{
                $trans = $page->translations["1"];
            }
            $response[] = ["name"   => $trans->name, "body"   => $trans->body];
        }

        return response()->json([
            'data' => $response
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function post_reset(Request $request)
    {
        $user = User::where('email', $request->input("email"))->firstOrFail();
        if ($user) {
            $completed = $this->auth->completeResetPassword($user, $request->input("code"), $request->new_password);

            if (!$completed) {
                return response()->json([
                    'message' => trans('user::messages.users.invalid_reset_code')
                ]);
            }

            return response()->json([
                'message' => trans('user::messages.users.password_has_been_reset')
            ]);
        } else {
            return response()->json([
                'message' => trans('user::messages.users.invalid_reset_code')
            ]);
        }
    }
}


