# PRZELEWY24 for Laravel #

If you are looking for function reach, secure and easy to use library for your Laravel powered application. You are in the right place. 

Library provides support for communication with [Przelewy24](https://przelewy24.pl) payment provider via [API](https://przelewy24.pl/storage/app/media/pobierz/Instalacja/przelewy24_dokumentacja_3.2.pdf) and [WebServices](https://przelewy24.pl/storage/app/media/pobierz/Instalacja/przelewy24_webservices.pdf).

__Please note:__
This library is still in early stage of development. Some functionalities may not work correctly or not work at all. Use on your own risk.

### Requirements ###
* PHP 5.6+
* Laravel 5.4+
* PHP modules: php-soap
* optionally [netborgteam/laravel-slack](https://bitbucket.org/netborgteam/laravel-slack) (for getting notification about payment events on Slack's channel)

### Installation ###
```
composer require netborg/laravel-przelewy24
composer update
```

Add Service Provider to your `config/app.php` file:
```php
	/*
    * Package Service Providers...
    */
	      
    // ....
    NetborgTeam\P24\Providers\P24Provider::class,
```

Manually copy `config/p24.php` config file to your `config` directory or execute:
```php
php artisan vendor:publish
```

Library comes with core database structure to handle payment requests. Optionally you may need to copy migration classes to your `database/migrations` folder if you require to customise database schema. 
Then execute database migrations:
```php
php artisan migrate
```

### Configuration ###

Provide your Merchant details in your `.env` config file:
```
P24_MERCHANT_ID=			// your MerchantId received from Przelewy24 ie. `123456`
P24_POS_ID=					// your PosId received from Przelewy24 (or copy your MerchantId from above)
P24_CRC=					// your CRC available in your panel on Przelewy24.
P24_API_KEY=				// your API KEY available in panel on Przelewy24.
P24_MODE=sandbox			// switch between test and production modes: `live` or `sandbox`
```

You may want to setup your custom route where your customers will be redirected to on transaction cancellation event. You may set it up in `config/p24.php` file:
```php
return [
    'merchant_id' => env('P24_MERCHANT_ID', 0),
    'pos_id' => env('P24_POS_ID', 0),
    'crc' => env('P24_CRC', null),
    'api_key' => env('P24_API_KEY', null),
    'mode' => env('P24_MODE', 'sandbox'),       
    'route_return' => null, // provide route name where Client shoud be redirected on transaction cancellation (you can override it on transaction registration)
];
```
That's it. You can start to manage payments from Przelewy24.

### Examples of usage ###

1. Transaction registration and payment initiation:
```php

public function registerTransaction(
	\Illuminate\Http\Request $request, 
	\NetborgTeam\P24\Services\P24Manager $manager
) { 

	// create new Transaction (for attribute reference please see P24 API docs)
	$transaction = new \NetborgTeam\P24\P24Transaction([
		// recommended way to provide session ID via supporting method
	    'p24_session_id' => \NetborgTeam\P24\P24Transaction::makeUniqueId($request->session()->getId()),
	    'p24_amount' => 19900,
	    'p24_currency' => 'PLN',
	    'p24_description' => 'Transaction description',
	    'p24_email' => 'client@netborg-software.com',
	    'p24_country' => 'PL',
	    'p24_url_return' => route('home'),
	]);

	try {
            $token = $manager->transaction($transaction)->register();
            if (is_string($token)) {
	            /* 
		           if transaction has been successfully registered
	               unique token will be returned - save it 
	               with transaction details 
                */
                $transaction->token($token);
                
				// then redirect customer to Przelewy24 to make payment
                return $transaction->redirectForPayment();
            } elseif (is_array($token)) {
                // some transaction attributes are incorrect - perform some action
            } else {
                // error
            }
        } catch (\NetborgTeam\P24\Exceptions\InvalidTransactionException $e) {
            // handle invalid transaction exception
        } catch (\NetborgTeam\P24\Exceptions\P24ConnectionException $e) {
            // handle connection to P24 server exception
        }
}
```

Uppon successfull payment, Przelewy24 will send notification to your listener.
No worries! Unless you have overrided `p24_url_status` with your custom URL, your app will handle all required checks for you. As default you will receive transaction status notifications on `/p24/status` URL.