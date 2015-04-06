<?php

/**
 * All adapters implementing payment functionality should implement this interface
 *
 * @date 18.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\services\payment;

/**
 * Interface AdapterInterface
 *
 * @package dlds\payment\services\payment
 */
interface AdapterInterface
{
    /**
     * Returns a dataset describing a payment form for this adapter
     *
     * @param Dataset $dataset
     * @param Transaction $transaction
     * @return Dataset
     */
    public function fillPaymentFormDataset(Dataset $dataset, Transaction $transaction);

    /**
     * Returns TRUE if an adapter can handle a payment response
     *
     * @param Response $response
     * @return boolean
     */
    public function canHandlePaymentResponse(Response $response);

    /**
     * Handles the payment response from the server.
     *
     * @param Response $response
     * @return void
     */
    public function handlePaymentResponse(Response $response);

    /**
     * Loads a transaction by the response's parameters
     *
     * @param Response $response
     * @param Transaction $transaction
     * @return void
     */
    public function loadTransactionFromResponse(Response $response, Transaction $transaction);
}