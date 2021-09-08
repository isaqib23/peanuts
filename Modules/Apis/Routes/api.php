<?php

use Illuminate\Support\Facades\Route;

Route::post('login', 'ApisController@login')->name('api.login');
Route::post('signup', 'ApisController@register')->name('register');
Route::post('products', 'ApisController@products')->name('products');
Route::post('product', 'ApisController@product')->name('product');
Route::post('add_to_cart', 'ApisController@addToCart')->name('addToCart');
Route::post('update_cart', 'ApisController@updateCart')->name('updateCart');
Route::post('delete_cart', 'ApisController@destroyCart')->name('destroyCart');
Route::post('show_cart', 'ApisController@cart')->name('cart');
Route::post('checkout', 'ApisController@checkout')->name('api.checkout');
Route::post('checkout_data', 'ApisController@checkoutData')->name('checkoutData');
Route::post('add_to_wishlist', 'ApisController@addToWishList')->name('addToWishList');
Route::post('wishlist', 'ApisController@wishlist')->name('wishlist');
Route::post('destroy_wishlist_item', 'ApisController@destroyWishlistItem')->name('destroyWishlistItem');
Route::post('orders', 'ApisController@orders')->name('orders');
Route::post('add_address', 'ApisController@storeAddress')->name('account.addresses.store');
Route::post('update_address', 'ApisController@updateAddress')->name('account.addresses.update');
Route::post('delete_address', 'ApisController@destroyAddress')->name('account.addresses.destroy');
Route::post('slides', 'ApisController@slides')->name('slides');
Route::post('votes', 'ApisController@votes')->name('votes');
Route::post('vote_cast', 'ApisController@voteCast')->name('voteCast');
Route::post('change_password', 'ApisController@changePassword')->name('changePassword');
Route::post('forgot_password', 'ApisController@postReset')->name('postReset');
Route::post('get_payment_token', 'ApisController@getPaymentToken')->name('getPaymentToken');
Route::post('complete_order', 'ApisController@completeOrder')->name('completeOrder');
Route::post('update_profile', 'ApisController@updateProfile')->name('updateProfile');
Route::post('clear_cart', 'ApisController@clearCart')->name('clearCart');
Route::post('direct_cart', 'ApisController@directCart')->name('directCart');
