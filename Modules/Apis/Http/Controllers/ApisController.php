<?php

namespace Modules\Apis\Http\Controllers;

use Carbon\Carbon;
use Cartalyst\Sentinel\Checkpoints\NotActivatedException;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Darryldecode\Cart\CartCollection;
use DB;
use GuzzleHttp\Client;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
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
use Modules\Coupon\Exceptions\MaximumSpendException;
use Modules\Coupon\Exceptions\MinimumSpendException;
use Modules\Media\Entities\File;
use Modules\Order\Entities\Order;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Page\Entities\Page;
use Modules\Payment\Facades\Gateway;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductLottery;
use Modules\Slider\Entities\Slider;
use Modules\Support\Country;
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

        $this->assignCustomerRole($user);

        $users = User::where("id",$user->id)->first();

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

        return response()->json([
            'data' => $users,
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

                $products[$key]->sold_items = (string) getSoldLottery($value->id);
                $products[$key]->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $value->id);
                $products[$key]->thumbnail_image = (!is_null($value->base_image->path)) ? $value->base_image : NULL;
                $products[$key]->suppliers = (!is_null($value->supplier->id)) ? $value->supplier : NULL;
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

        $product->sold_items = (string) getSoldLottery($product->id);
        $product->is_added_to_wishlist = isAddedToWishlist($request->input('user_id'), $product->id);
        $product->thumbnail_image = (!is_null($product->base_image->path)) ? $product->base_image : NULL;
        $product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
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
        $getLottery = ProductLottery::where("product_id",$request->product_id)->first();
        $soldTickets = getSoldLottery($request->product_id);
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

        if($getLottery){
            $remainingTickets = (int)$getLottery->max_ticket - (int)$soldTickets;
            if($request->qty > $remainingTickets) {
                return response()->json([
                    'message' => "You can buy ".(int)$remainingTickets." items for this product",
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
            DB::table("user_cart")->where([
                "user_id"       => $request->input('user_id'),
                "product_id"    => $request->product_id
            ])->update([
                "qty"   => (int) $request->qty + (int) $userCart->qty
            ]);
        }else {
            DB::table("user_cart")->insert([
                "user_id" => $request->input('user_id'),
                "qty" => $request->qty,
                "product_id" => $request->product_id,
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
                        $cartArray["items"][$key1]->product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
                    }
                }
            }

            return $cartArray;
        }

        return [];
    }

    /**
     * @param Request $request
     * @return CartCollection
     */
    public function cart(Request $request)
    {
        Cart::clear();
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)){
            foreach ($userCart as $cart) {
                $getProduct = Product::getProductById($cart->product_id);
                if($getProduct && $getProduct->product_type == 1) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }
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
                        $cartArray["items"][$key1]->product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
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
    public function directCart(Request $request)
    {
        Cart::clear();
        $userCart = DB::table("user_cart")->where("user_id", $request->input('user_id'))->get();
        if(!is_null($userCart)){
            foreach ($userCart as $cart) {
                $getProduct = Product::getProductById($cart->product_id);
                if($getProduct && $getProduct->product_type == 0) {
                    Cart::store($cart->product_id, $cart->qty, json_decode($cart->options) ?? []);
                }
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
                        $cartArray["items"][$key1]->product->suppliers = (!is_null($product->supplier->id)) ? $product->supplier : NULL;
                    }
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

        $data = [
            'countries' => $countries,
            'gateways' => $gateways,
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
            $data[$key]->suppliers = (!is_null($value->supplier->id)) ? $value->supplier : NULL;

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
                            $products[$proKey]['sold_items'] = (string)getSoldLottery($value["id"]);
                            $products[$proKey]['is_added_to_wishlist'] = isAddedToWishlist($request->input('user_id'), $value["id"]);
                            $products[$proKey]['thumbnail_image'] = (!isset($value["base_image"]["path"])) ? $value["base_image"] : NULL;
                            $products[$proKey]['suppliers'] = (!isset($value["supplier"]["id"])) ? $value["supplier"] : NULL;
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
     * @param Request $request
     * @return JsonResponse
     */
    public function votes(Request $request){
        $votes = Votes::all();
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
            }
        }
        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function createPayment(Request $request){
        $user = User::where("id",$request->input('user_id'))->first();
        $userAddress = Address::where("id",$request->input('address'))->first();

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

        $postData = new \StdClass();
        $postData->action = "PURCHASE";
        $postData->amount = new \StdClass();
        $postData->merchantAttributes = new \StdClass();
        $postData->billingAddress = new \StdClass();
        $postData->amount->currencyCode = "AED";
        $postData->amount->value = bcmul($request->input('amount'),100);
        $postData->merchantAttributes->redirectUrl = "https://itspeanutsdev.com/order_confirmation";
        $postData->merchantAttributes->skipConfirmationPage = true;
        $postData->merchantAttributes->merchantOrderReference = $request->input('user_id')."-".$request->input('address');
        $postData->emailAddress = $user->email;
        $postData->billingAddress->firstName = $userAddress->first_name;
        $postData->billingAddress->lastName = $userAddress->last_name;
        $postData->billingAddress->address1 = $userAddress->address_1;
        $postData->billingAddress->city = $userAddress->city;
        $postData->billingAddress->countryCode = $userAddress->country;

        $outlet = env('NETWORK_OUTLET');
        $token = $access_token;

        $json = json_encode($postData);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/".$outlet."/orders");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$token,
            "Content-Type: application/vnd.ni-payment.v2+json",
            "Accept: application/vnd.ni-payment.v2+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $output = json_decode(curl_exec($ch));
        $order_reference = $output->reference;
        $order_paypage_url = $output->_links->payment->href;

        curl_close ($ch);

        return response()->json([
            'data' => [
                "order_reference"       => $order_reference,
                "order_payment_url"     => $order_paypage_url."&slim=true",
            ],
        ]);
    }

    public function order_confirmation(Request $request, OrderService $orderService){
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
        dd($output);
        $callBackData = $output->merchantAttributes->merchantOrderReference;
        $user_id = strtok($callBackData, '-');
        $address_id = substr($callBackData, strpos($callBackData, "-") + 1);

        $user = User::where("id",$user_id)->first();
        $userAddress = Address::where("id",$address_id)->first();
        $request->merge([
            "user_id"   => $user_id,
            "transaction_id"   => $orderRef,
            "customer_email"   => $user->email,
            "customer_phone"   => $user->phone,
            "billing"   => $userAddress->toArray(),
            "billingAddressId"   => $address_id,
            "shippingAddressId"   => $address_id,
            "newBillingAddress"   => false,
            "newShippingAddress"   => false,
            "payment_method"   => "foloosi",
        ]);

        $order = $orderService->create($request);

        $orderId = $order->id;

        event(new OrderPlaced($order));

        updateProductLottery($orderId);

        $order = Order::findOrFail($orderId);

        $order->storeFoloosiTransaction($request->input('transaction_id'));

        $order->update(['status' => "completed"]);

        DB::table("user_cart")->where("user_id", $request->input('user_id'))->delete();

        curl_close ($ch);

        return redirect('/home');exit;
    }
}


