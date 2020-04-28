<?php

Route::group([
    'prefix' => 'p24',
    'namespace' => 'NetborgTeam\P24\Controllers',
    'middleware' => ['web']
], function () {
    Route::post('/status', 'P24ListenerController@getTransactionStatus')->name('getTransactionStatusListener');
    Route::get('/return', 'P24ListenerController@getReturn')->name('getTransactionReturn');
    
});
