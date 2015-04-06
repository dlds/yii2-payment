<?php

/**
 * Contains the logic associated with payment services: generating the payment
 * form and accepting the response from server
 *
 * @date 19.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\services;

use dlds\payment\Exception;
use dlds\payment\PaymentHandlerBase;
use dlds\payment\services\payment\Form;
use dlds\payment\services\payment\Response;
use dlds\payment\services\payment\Transaction;

/**
 * Class Payment
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\services
 */
class Payment extends ServicesAbstract
{
    /**
     * @inheritdoc
     */
    public function getServiceCode()
    {
        return PaymentHandlerBase::SERVICE_PAYMENT;
    }

    /**
     * Generates payment forms for all enabled adapters
     *
     * @param Transaction $transaction
     * @return Form[]
     */
    public function generateForms(Transaction $transaction)
    {
        $paymentForms = array();

        foreach ($this->paymentAdapters as $bankId => $paymentAdapter) {
            if ($paymentAdapter->enabled) {
                $form = new Form([], $paymentAdapter);

                $paymentAdapter->fillPaymentFormDataset($form, $transaction);
                $this->paymentHandler->finalizeForm($form, $paymentAdapter);

                $paymentForms[$bankId] = $form;
            }
        }

        return $paymentForms;
    }

    /**
     * Handles the response received from the server after payment
     *
     * @param array $request Data from $_REQUEST
     * @return Response
     * @throws \dlds\payment\Exception
     */
    public function handleResponse(array $request)
    {
        foreach ($this->paymentAdapters as $adapter) {
            $response = new Response($request, $adapter);
            if ($adapter->enabled && $adapter->canHandlePaymentResponse($response)) {
                $adapter->handlePaymentResponse($response);

                $transaction = new Transaction();
                $adapter->loadTransactionFromResponse($response, $transaction);
                $response->setTransaction($transaction);
                return $response;
            }
        }
        throw new Exception("No adapters found that could handle the payment response");
    }

    /**
     * Create a new transaction object
     *
     * @param mixed $transactionId
     * @param float $sum
     * @param array $params
     * @return Transaction
     */
    public function createTransaction($transactionId, $sum, $params = [])
    {
        return Transaction::create($transactionId, $sum, $params);
    }
}