<?php

use Illuminate\Support\Facades\Route;

Route::post('login', 'ApisController@login')->name('login');
Route::post('signup', 'ApisController@register')->name('register');
Route::post('products', 'ApisController@products')->name('products');
Route::post('product', 'ApisController@product')->name('product');
Route::post('add_to_cart', 'ApisController@addToCart')->name('addToCart');
Route::post('update_cart', 'ApisController@updateCart')->name('updateCart');
Route::post('delete_cart', 'ApisController@destroyCart')->name('destroyCart');
Route::post('show_cart', 'ApisController@cart')->name('cart');
Route::post('checkout', 'ApisController@checkout')->name('checkout');
Route::post('checkout_data', 'ApisController@checkoutData')->name('checkoutData');
Route::post('add_to_wishlist', 'ApisController@addToWishList')->name('addToWishList');
Route::post('wishlist', 'ApisController@wishlist')->name('wishlist');
Route::post('destroy_wishlist_item', 'ApisController@destroyWishlistItem')->name('destroyWishlistItem');
Route::post('orders', 'ApisController@orders')->name('orders');
