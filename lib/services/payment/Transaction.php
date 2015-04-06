<?php

/**
 * Represents a financial transaction
 *
 * @date 19.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\services\payment;

use dlds\payment\Exception;
use dlds\payment\helpers\PaymentHelper;
use dlds\payment\PaymentHandlerBase;
use yii\base\Object;

/**
 * Class Transaction
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\services\payment
 *
 * @method string getSum($default = null)
 * @method string getComment($default = null)
 * @method string getTransactionId($default = null)
 * @method string getReference($default = null)
 * @method string getCurrency($default = null)
 * @method string getLanguage($default = null)
 *
 * @method boolean hasSum()
 * @method boolean hasComment()
 * @method boolean hasTransactionId()
 * @method boolean hasReference()
 * @method boolean hasCurrency()
 * @method boolean hasLanguage()
 */
class Transaction extends Object
{
    /**
     * @var array
     */
    protected $params = [];

    /**
     * Create a new transaction object
     *
     * @param mixed $transactionID
     * @param float $sum
     * @param array $params
     * @return self
     */
    public static function create($transactionID, $sum, $params = [])
    {
        $transaction = new self;
        $transaction->setTransactionID($transactionID)
            ->setSum($sum)
            ->setCurrency(PaymentHandlerBase::DEFAULT_CURRENCY)
            ->setLanguage(PaymentHandlerBase::DEFAULT_LANGUAGE);
        foreach ($params as $key => $value)
        {
            $transaction->params[$key] = $value;
        }
        return $transaction;
    }

    /**
     * Sets the transaction language
     *
     * @param string $language
     * @return Transaction
     */
    public function setLanguage($language)
    {
        $this->params['language'] = $language;
        return $this;
    }

    /**
     * Sets the transaction currency
     *
     * @param string $currency
     * @return self
     */
    public function setCurrency($currency)
    {
        $this->params['currency'] = $currency;
        return $this;
    }

    /**
     * Set the transaction sum
     *
     * @param float $sum
     * @return Transaction
     */
    public function setSum($sum)
    {
        $sum = (float)str_replace(',', '.', $sum);
        $this->params['sum'] = $sum;
        return $this;
    }

    /**
     * Set the transaction ID
     *
     * @param int $transactionId
     * @throws \dlds\payment\Exception If transaction ID is not numeric
     * @return Transaction
     */
    public function setTransactionId($transactionId)
    {
        if (is_numeric($transactionId) || $transactionId === false) {
            $this->params['transactionId'] = $transactionId;
            return $this;
        }
        throw new Exception("Please use a numeric value for transaction ID");
    }

    /**
     * @return string
     */
    function __toString()
    {
        return \var_export($this->params, true);
    }

    /**
     * Sets the transaction comment
     *
     * @param string $comment
     * @return Transaction
     */
    public function setComment($comment)
    {
        $this->params['comment'] = $comment;
        return $this;
    }

    /**
     * Sets the payment reference
     *
     * @param mixed $reference If NULL, the reference will be generated automatically from Transaction ID
     * @return Transaction
     */
    public function setReference($reference = null)
    {
        $reference === null && $reference = PaymentHelper::generateReference($this->getTransactionId());
        $this->params['reference'] = $reference;
        return $this;
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function __call($name, $params)
    {
        $property = lcfirst(substr($name, 3));
        if (substr($name, 0, 3) === 'get') {
            if (isset($this->params[$property])) {
                return $this->params[$property];
            } elseif (isset($params[0])) {
                return $params[0];
            }
        } elseif (substr($name, 0, 3) === 'has') {
            return isset($this->params[$property]);
        }
        return parent::__call($name, $params);
    }
}
