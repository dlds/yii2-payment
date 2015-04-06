<?php
require 'yii_bootstrap.php';

require 'PaymentHandler.php';

$service = PaymentHandler::createPaymentService();

// create a demo transaction
$transaction = $service->createTransaction(14, 35, ['comment' => 'Comment']);
$transaction->setReference();

echo '<h1>Transaction</h1>' . $transaction . '<h1>Submit forms</h1>';

echo \dlds\payment\widgets\PaymentWidget::widget([
    'forms' => $service->generateForms($transaction),
    'debug' => true,
]);





