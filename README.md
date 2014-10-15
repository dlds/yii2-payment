PaymentHandler extension for Yii2
============

Library to handle payments with Estonian banks. Main features includes:
- Payment service implemented with the following handlers: IPizza (Swedbank, SEB, LHV, Danske, Krediidipank), Estcard, Nordea
- Object oriented and extensible code, you can easily add custom adapters
- Generates payment forms from transaction objects
- Handles bank post-back requests

This is a very basic "low-level" component for implementing payments. If you also need functionality for shopping baskets and orders, you might want to look at [opus-online/yii2-ecom](https://github.com/opus-online/yii2-ecom) extension.

## Installation
Just define the required package in your `composer.json`. 
```json
{
	"require": {
		"opus-online/yii2-payment": "*",
	}
}
```

## Usage
Overwrite `PaymentHandlerBase` class and at least the method `getConfiguration`. Complement the two configuration files
with your account details and locations of key and certificate files. You can just copy the files from under `examples/config`.

```php
class MyPaymentHandler extends \opus\payment\PaymentHandlerBase
{

    /**
     * Returns the configuration array
     */
    public function getConfiguration()
    {
        return [
            // common parameters for all adapters
            'common' => array(
                'returnRoute' => 'bankController/return',
            ),
            // adapter-specific parameters
            'adapters' => \yii\helpers\ArrayHelper::merge(require 'banks-default.php', require 'banks-local.php')
        ];
    }
}
```

### Render all payment forms
```php
// create the payment service
$service = MyPaymentHandler::createPaymentService();

// create a transaction object
$transaction = $service->createTransaction(1234, 234, ['comment' => 'Comment']);

// render the widget
echo \opus\payment\widgets\PaymentWidget::widget([
    'forms' => $service->generateForms($transaction),
]);

```
#### Customize the payment form
The default implementation uses the widget `opus\payment\widgets\PaymentWidget`. You can override and use this to use custom forms or elements. 
```php
class MyPaymentWidget extends PaymentWidget
{
    protected function generateSubmit(Form $form) {
        return Html::submitButton('MyCustomLabel');
    }
}
```

And then just render your customized widget instead of the default one (see the widget example above).

### Receive return requests from the bank
```php
$service = PaymentHandler::createPaymentService();
$response = $service->handleResponse($_REQUEST);

// re-generate the transaction object from the response
$transaction = $response->getTransaction();

if ($response->isSuccessful()) {
	// see the Transaction object for more details
	echo $transaction->getTransactionId();
}
```

Added `cancelRoute` and `getCancelUrl()` to AdapterAbstract to allow different redirecting after cancelling a payment
