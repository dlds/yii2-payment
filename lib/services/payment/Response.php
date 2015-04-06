<?php

/**
 * Represents a communications response
 *
 * @date 19.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */

namespace dlds\payment\services\payment;

/**
 * Class Response
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\services\payment
 */
class Response extends Dataset
{
    /** @var boolean TRUE if response was successful */
    protected $isSuccessful = false;
    /** @var boolean TRUE if response was automatically generated */
    protected $isAutomatic = null; // can be not set at all
    /** @var Transaction Reference to the transaction */
    protected $transaction;

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return [
            'isSuccessful' => $this->isSuccessful(),
            'isAutomatic' => $this->isAutomatic(),
            'response' => parent::toArray(),
        ];
    }

    /**
     * Sets the response as successful or not
     *
     * @param boolean $success
     */
    public function setIsSuccessful($success = true)
    {
        $this->isSuccessful = $success;
    }

    /**
     * Returns TRUE if the request (corresponding to this response) was successful
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return $this->isSuccessful;
    }

    /**
     * Returns TRUE if the response was sent by the bank automatically. NULL means
     * that the response type could not be determined
     *
     * @return boolean
     */
    public function isAutomatic()
    {
        return $this->isAutomatic;
    }

    /**
     * Sets the response type as automatic or not
     *
     * @param boolean $isAutomatic
     */
    public function setIsAutomatic($isAutomatic)
    {
        $this->isAutomatic = $isAutomatic;
    }

    /**
     * Returns the transaction object. Not that this is put together from the
     * variables the server is kind enough to return (no sessions), so it is
     * SIMILAR to the object created to generate the form.
     *
     * Notable differences: transaction ID and message are missing if using Estcard.
     *
     * @return Transaction
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Sets the related transaction reference
     *
     * @param Transaction $transaction
     */
    public function setTransaction(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }
}