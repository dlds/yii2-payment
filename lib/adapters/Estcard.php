<?php

/**
 * Handles payment actions using Estcard services (e-commerce)
 * Specification: http://www.estcard.ee/publicweb/html/est/e-commerce.html
 *
 * @date 18.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\adapters;

use dlds\payment\PaymentHandlerBase;
use dlds\payment\services\payment\AdapterInterface;
use dlds\payment\services\payment\Dataset;
use dlds\payment\services\payment\Response;
use dlds\payment\services\payment\Transaction;
use yii\helpers\ArrayHelper;

/**
 * Class Estcard
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\adapters
 */
class Estcard extends AbstractAdapter implements AdapterInterface
{
    /**
     * @inheritdoc
     */
    public $name = 'Credit Card';

    /**
     * @inheritdoc
     */
    public function getRequiredConfParams()
    {
        return ArrayHelper::merge(
            parent::getRequiredConfParams(),
            array(
                'id' => 'string',
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function fillPaymentFormDataset(Dataset $dataset, Transaction $transaction)
    {
        // add some common fields
        $dataset
            ->setParam('action', 'gaf')
            ->setParam('ver', 4)
            ->setParam('datetime', date("YmdHis"));
        // convert amount into cents
        $amount = (int)($transaction->getSum() * 100);

        // add transaction information
        /** @noinspection SpellCheckingInspection */
        $dataset
            ->setParam('id', $this->getConfParam('id'))
            ->setParam('ecuno', $transaction->getTransactionId())
            ->setParam('eamount', $amount)
            ->setParam('cur', $transaction->getCurrency())
            ->setParam('lang', $this->getLanguageCode($transaction->getLanguage()))
            ->setParam('charEncoding', $this->getConfParam('charEncoding', 'UTF-8'))
            ->setParam(
                'feedBackUrl',
                $this->getReturnUrl()
            ) // return_url
            ->setParam('delivery', $this->getConfParam('delivery', 'S'));

        if (intval(($ecuno = $dataset->getParam('ecuno', 0))) < 100000) {
            $dataset->setParam('ecuno', $ecuno + 100000000); // add 100 000 000
        }
        $this->addMacSignature($dataset);
    }

    /**
     * Estcard supports et, en, fi, de. Our system supports en, ru, et.
     *
     * @param string $language
     * @return string
     */
    public function getLanguageCode($language)
    {
        switch ($language) {
            case PaymentHandlerBase::LANGUAGE_EN:
            case PaymentHandlerBase::LANGUAGE_ET:
                return $language;
            default:
                return PaymentHandlerBase::LANGUAGE_EN;
        }
    }

    /**
     * Generates a MAC key that corresponds to the parameter set specified by
     * the first parameter and adds it to the dataset (parameter 'mac')
     *
     * @param \dlds\payment\services\payment\Dataset $dataset
     * @return string
     */
    private function addMacSignature(Dataset $dataset)
    {
        $macSource = $this->getMacSource($dataset, 'payment');
        $keyPath = $this->getPkcKeyPath();

        $signature = $this->signWithPrivateKey($keyPath, $macSource);

        $mac = bin2hex($signature);
        $dataset->setParam('mac', $mac);

        // Yes, the ID value in the form and the value in MAC are different
        $dataset->setParam('id', trim($dataset->getParam('id')));
        $dataset->setParam('feedBackUrl', trim($this->getReturnUrl()));
    }

    /**
     * Calculates MAC source for Estcard adapter (basic implode)
     *
     * @param \dlds\payment\services\payment\Dataset $dataset
     * @param string $requestType
     * @return string
     */
    private function getMacSource(Dataset $dataset, $requestType)
    {
        $this->formatParams($dataset);
        $macParams = $this->getNormalizedMacParams($dataset, $requestType);
        $source = implode($macParams);

        return $source;
    }

    /**
     * @inheritdoc
     */
    public function loadTransactionFromResponse(Response $response, Transaction $transaction)
    {
        $transaction->setComment(false);
        $ecuno = $response->getParam('ecuno', false);
        if ($ecuno > 100000000) {
            $ecuno = $ecuno % 100000000; // subtract 100 000 000
        }
        $transaction->setTransactionID($ecuno);
        $transaction->setSum($response->getParam('eamount', false));
        $transaction->setCurrency($response->getParam('cur', false));
        $transaction->setLanguage($response->getParam('lang', false));
    }

    /**
     * @inheritdoc
     */
    public function canHandlePaymentResponse(Response $response)
    {
        return 'afb' == $response->getParam('action', false); // go figure
    }

    /**
     * @inheritdoc
     */
    public function handlePaymentResponse(Response $response)
    {
        $macSource = $this->getMacSource($response, 'payment_response');
        $certPath = $this->getPkcCertPath();

        // we don't have hex2bin until PHP 5.4.0, so this will do
        $macBin = pack("H*", $response->getParam('mac'));

        // this throws an exception if necessary
        $this->verifySignatureWithCertificate($certPath, $macSource, $macBin);

        /** @noinspection SpellCheckingInspection */
        if ($response->hasParam('respcode') && $response->getParam('respcode') === '000') {
            $response->setIsSuccessful(true);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getParamDefinition()
    {
        /** @noinspection SpellCheckingInspection */
        return array(
            'action' => array('length' => 3),
            'ver' => array('length' => 3, 'format' => '%03s'),
            'id' => array('length' => 10, 'format' => '%-10s'),
            'ecuno' => array('length' => 12, 'format' => '%012s'),
            'eamount' => array('length' => 12, 'format' => '%012s'),
            'cur' => array('length' => 3, 'format' => '%3s'),
            'datetime' => array('length' => 14),
            'feedBackUrl' => array('length' => 128, 'format' => '%-128s'),
            'delivery' => array('length' => 1, 'format' => '%1s'),
            'charEncoding' => array('length' => 10),
            'respcode' => array('length' => 3, 'format' => '%-3s', 'mb_str_pad' => true, 'pad' => STR_PAD_RIGHT),
            // response only
            'receipt_no' => array(
                'length' => 6,
                'format' => '%06s',
                'mb_str_pad' => true,
                'pad' => STR_PAD_LEFT,
                'padChar' => '0'
            ),
            // response only
            'actiontext' => array('length' => 40, 'format' => '%-40s', 'mb_str_pad' => true, 'pad' => STR_PAD_RIGHT),
            // response only
            'msgdata' => array('length' => 40, 'format' => '%-40s', 'mb_str_pad' => true, 'pad' => STR_PAD_RIGHT),
            // response only
            'mac' => array('length' => 512),
            // length actually not specified
        );
    }

    /**
     * @inheritdoc
     */
    protected function getParamMacOrderDefinition()
    {
        /** @noinspection SpellCheckingInspection */
        return array(
            // request to make a transaction
            'payment' => array(
                'ver',
                'id',
                'ecuno',
                'eamount',
                'cur',
                'datetime',
                'feedBackUrl',
                'delivery'
            ),
            // response to the request to make a transaction
            'payment_response' => array(
                'ver',
                'id',
                'ecuno',
                'receipt_no',
                'eamount',
                'cur',
                'respcode',
                'datetime',
                'msgdata',
                'actiontext'
            )
        );
    }
}

