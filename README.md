# PRZELEWY24 for Laravel #

Library provides integration with [Przelewy24](https://przelewy24.pl) payment provider via [API](https://przelewy24.pl/storage/app/media/pobierz/Instalacja/przelewy24_dokumentacja_3.2.pdf) (API v3.2) and [WebServices](https://przelewy24.pl/storage/app/media/pobierz/Instalacja/przelewy24_webservices.pdf) (Web Services v2.5.0).

__Please note:__
This library is still in early stage of development. Some functionalities may not work correctly or not work at all. Use on your own risk.

### Requirements ###
* PHP 7.1.3+
* Laravel 6.0+
* PHP modules: php-soap, php-json
* requires HTTPS

@memeller - Updated dependencies for Laravel 7,8 and php 8 (illuminate/support and others are now a part of laravel/framework) - everything seems to be working fine, but I can't guarantee that everything is working fine. Use at your own risk.

### Installation ###
```
composer require netborg/laravel-przelewy24 "~3.0"
composer update
```

Publish P24 config file:
```shell
php artisan vendor:publish --tag="p24-config"
```

Library comes with core database structure to handle payment requests. Optionally you may need to copy migration classes to your `database/migrations` folder for further database schema customization. 
Execute database migrations:
```shell
php artisan vendor:publish --tag="p24-migrations"   // (optional) - if you want to publish and customize database schema before `php artisan migrate` command execution.
php artisan migrate     // always execute this command (even if you have not required optional migration publishing above)
```

### Configuration ###

Provide your Merchant details in your `.env` config file:
```dotenv
P24_MERCHANT_ID=    // your MerchantId received from Przelewy24 ie. `123456`
P24_POS_ID=         // your PosId received from Przelewy24 (or copy your MerchantId from above)
P24_CRC=            // your CRC available in your panel on Przelewy24.
P24_API_KEY=        // your API KEY available in panel on Przelewy24.
P24_MODE=sandbox    // switch between test and production modes: `live` or `sandbox`
```

You may want to setup your custom route where your customers will be redirected to on transaction cancellation event. You may set it up in `config/p24.php` file:
```php
return [
    'merchant_id' => env('P24_MERCHANT_ID', 0),
    'pos_id' => env('P24_POS_ID', 0),
    'crc' => env('P24_CRC', null),
    'api_key' => env('P24_API_KEY', null),
    'mode' => env('P24_MODE', 'sandbox'),
    
    /*  
        provide route name (or URL starting with `http://` or `https://`) where Client shoud be redirected 
        on transaction cancellation or after payment completion (you can override it on transaction registration)
    */
    'route_return' => null,
];
```
That's it. You can start to manage payments from Przelewy24.

### Events ###

Although, all major transaction events are supported and maintained out of the box, you may want to subscribe to various events and perform some custom actions. For this purpose [create and register](https://laravel.com/docs/5.8/events#registering-events-and-listeners) your custom event listeners.
Subscribe to any of the events listed below:

```php
    \NetborgTeam\P24\Events\P24TransactionUserReturnedEvent::class
    \NetborgTeam\P24\Events\P24TransactionConfirmationConnectionErrorEvent::class
    \NetborgTeam\P24\Events\P24TransactionConfirmationInvalidParameterEvent::class
    \NetborgTeam\P24\Events\P24TransactionConfirmationInvalidSenderEvent::class
    \NetborgTeam\P24\Events\P24TransactionConfirmationInvalidSignatureEvent::class
    \NetborgTeam\P24\Events\P24TransactionConfirmationSuccessEvent::class
```

- `\NetborgTeam\P24\Events\P24TransactionUserReturnedEvent::class`: This event is being triggered whenever user cancels or finishes payment process and is being redirected back to the application. This event **will not be be triggered** if you have overridden `p24_url_return` parameter with your custom URL on transaction registration.
- `\NetborgTeam\P24\Events\P24TransactionConfirmationConnectionErrorEvent::class`: This event is being triggered if there was a problem with connection to _Przelewy24_'s servers at the moment an app intended to send back transaction confirmation for verification.
- `\NetborgTeam\P24\Events\P24TransactionConfirmationInvalidParameterEvent::class`: This event may be triggered very occasionally if any of core Transaction parameter values don't match the values of Transaction Confirmation received from `Przelewy24` servers. Could be a signal of a fraud attempt.
- `\NetborgTeam\P24\Events\P24TransactionConfirmationInvalidSenderEvent::class`: This event may be triggered very occasionally in case if Transaction Confirmation was send from IP address not listed [here](https://docs.przelewy24.pl/P%C5%82atno%C5%9Bci_internetowe#2.8_Adresy_IP_serwer.C3.B3w).
- `\NetborgTeam\P24\Events\P24TransactionConfirmationInvalidSignatureEvent::class`: This event is being triggered if Transaction Confirmation's signature is invalid. Could be a signal of a fraud attempt.
- `\NetborgTeam\P24\Events\P24TransactionConfirmationSuccessEvent::class`: This event is being triggered when Transaction is successfully verified and confirmed with `Przelewy24` servers. It is recommended to subscribe to this event for custom processing of `confirmed transactions`.

### Examples of usage ###

#### API calls ####
##### Transaction registration and customer payment initiation: #####
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
	]);

	try {
            $token = $manager->register($transaction);
            
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

Upon successful payment, Przelewy24 will send notification to your listener.
No worries! Unless you have overridden `p24_url_status` with your custom URL, your app will handle all required checks for you. As default you will receive transaction status notifications on URL `https://[YOUR_DOMAIN]/p24/status`.

#### Przelewy24 Web Service calls ####
To call any of Przelewy24's Web Service methods in your app, use `P24WebServicesManager` service.
You can simply get an instance of this service either by directly calling it from container or by calling it indirectly using `P24Manager`:
```php
// direct call
$wsManager = app()->make(\NetborgTeam\P24\Services\P24WebServicesManager::class);

// get service instance via $manager
$manager = app()->make(\NetborgTeam\P24\Services\P24Manager::class);
$wsManager = $manager->webServices();
``` 

##### Test access to Web Service #####

```php
$result = $wsManager->testAccess();     // returns bool `true` if accessed successfully, `false` otherwise
```

##### Get list of available Payment Methods #####

```php
$list = $wsManager->getPaymentMethods()->result();  // returns an array of `PaymentMethod` instances.
```

##### Get transaction details by `p24_session_id` #####

```php
$transaction = $wsManager->getTransactionBySessionId('SESSION_ID')->result();  // where `SESSION_ID` is a `p24_session_id` parameter provided while transaction registration.
```

##### Make transaction refunds #####

```php
// build an `ArrayOfRefunds`
$refunds = (new \NetborgTeam\P24\ArrayOfRefund())
    // add transaction refund details 
    // either by providing direct key values
    ->addByKeys('SESSION_ID', ORDER_ID, AMOUNT)
    
    // or by passing `SingleRefund` objects  
    ->add(new \NetborgTeam\P24\SingleRefund([
        'sessionId' => 'SESSION_ID',
        'orderId' => (int) ORDER_ID,
        'amount' => (int) AMOUNT,
    ]));
    
$results = $wsManager->refund(BATCH_ID, $refunds)->result();    // returns a list of refund results (see P24 Web Services docs)
```

_... more to come soon_
