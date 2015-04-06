<?php /** @noinspection SpellCheckingInspection */

/**
 * Handles payment actions for Nordea bank
 * Specification: http://www.nordea.ee/sitemod/upload/root/www.nordea.ee%20-%20default/Teenused%20firmale/E-Payment_v1_1.pdf
 * Since Nordea supports only iso-8859-1 charsets, all other characters will be transliterated.
 *
 * @date 18.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\adapters;

use dlds\payment\Exception;
use dlds\payment\PaymentHandlerBase;
use dlds\payment\services\payment\AdapterInterface;
use dlds\payment\services\payment\Dataset;
use dlds\payment\services\payment\Response;
use dlds\payment\services\payment\Transaction;
use yii\helpers\ArrayHelper;

/**
 * Class Nordea
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\adapters
 */
class Nordea extends AbstractAdapter implements AdapterInterface
{
    /**
     * @var string Defines the default hash function to use
     */
    const DEFAULT_HASH_FUNCTION = 'md5';
    /**
     * @inheritdoc
     */
    public $name = 'Nordea';
    /**
     * @inheritdoc
     */
    public $charset = 'iso-8859-1';
    /**
     * @var array Defines the hash functions that are allowed in the configuration
     */
    public $hashFunctions = array('sha1', 'md5'); // actual hash function is defined in the contract

    /**
     * @inheritdoc
     */
    public function getRequiredConfParams()
    {
        /** @noinspection SpellCheckingInspection */
        return ArrayHelper::merge(
            parent::getRequiredConfParams(),
            array(
                'SOLOPMT_RCV_ID' => 'string',
                'MAC_SECRET' => 'string',
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function fillPaymentFormDataset(Dataset $dataset, Transaction $transaction)
    {
        /** @noinspection SpellCheckingInspection */

        $dataset
            ->setParam('SOLOPMT_VERSION', '0003')
            ->setParam('SOLOPMT_STAMP', $transaction->getTransactionId())
            ->setParam('SOLOPMT_RCV_ID', $this->getConfParam('SOLOPMT_RCV_ID'))
            ->setParam('SOLOPMT_LANGUAGE', $this->getConfParam('SOLOPMT_LANGUAGE'))
            ->setParam('SOLOPMT_AMOUNT', $transaction->getSum())
            ->setParam('SOLOPMT_DATE', 'EXPRESS')
            ->setParam('SOLOPMT_RETURN', $this->getReturnUrl())
            ->setParam('SOLOPMT_CANCEL', $this->getReturnUrl())
            ->setParam('SOLOPMT_REJECT', $this->getReturnUrl())
            ->setParam('SOLOPMT_CONFIRM', 'YES')
            ->setParam('SOLOPMT_KEYVERS', $this->getConfParam('SOLOPMT_KEYVERS', '0001'))
            ->setParam('SOLOPMT_CUR', $transaction->getCurrency());

        // add reference if set
        if ($transaction->hasReference()) {
            $dataset->setParam('SOLOPMT_REF', $transaction->getReference());
        } elseif (false !== $this->getConfParam('SOLOPMT_REF', false)) {
            $dataset->setParam('SOLOPMT_REF', $this->getConfParam('SOLOPMT_REF'));
        }

        // add comment if set
        if ($transaction->hasComment()) {
            $dataset->setParam('SOLOPMT_MSG', $transaction->getComment());
        } elseif (false !== $this->getConfParam('SOLOPMT_MSG', false)) {
            $dataset->setParam('SOLOPMT_MSG', $this->getConfParam('SOLOPMT_MSG'));
        }

        // add language if set
        if ($transaction->getLanguage() !== $this->getConfParam('SOLOPMT_LANGUAGE')) {
            $dataset->setParam('SOLOPMT_LANGUAGE', $this->getLanguageCode($transaction->getLanguage()));
        }

        $this->formatParams($dataset);

        $mac = $this->generateMacKey($dataset, 'payment');
        $dataset->setParam('SOLOPMT_MAC', $mac);

        return $dataset;
    }

    /**
     * @inheritdoc
     * @todo: resolve
     */
    public function getConfParamaaaaaa($param, $default = null)
    {
        $value = parent::getConfParam($param, $default);
        // XXX :: pangalink.net creates wrong/bad return url: https://github.com/andris9/mockup.node.ee/issues/14
        // Nordea tech support confirmed that they do not need ? at the end of URI
        if (('return_url' == $param)
            && (false !== strpos(parent::getConfParam('url'), 'pangalink.net'))
            && (false === strpos($value, '?'))
        ) {
            $value .= '?';
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCode($language)
    {
        switch ($language) {
            case PaymentHandlerBase::LANGUAGE_ET:
                return 4;
            case PaymentHandlerBase::LANGUAGE_EN:
            default:
                return 3;
        }
    }

    /**
     * Generates a MAC key that corresponds to the parameter set specified by the first parameter.
     *
     * @param Dataset $dataset
     * @param string $requestType Key of 'mac param order definition' array
     * @return string
     */
    private function generateMacKey(Dataset $dataset, $requestType)
    {
        $macDefinition = $this->getParamMacOrderDefinition();
        $hashFunction = $this->getConfParam('hash_function', self::DEFAULT_HASH_FUNCTION);
        $macKey = '';
        foreach ($macDefinition[$requestType] as $param) {
            $macKey .= sprintf('%s&', $dataset->hasParam($param) ? $dataset->getParam($param) : null);
        }
        $macKey .= $this->getConfParam('MAC_SECRET') . '&';
        $macKey = \strtoupper($hashFunction($macKey));

        return $macKey;
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
                'SOLOPMT_VERSION',
                'SOLOPMT_STAMP',
                'SOLOPMT_RCV_ID',
                'SOLOPMT_AMOUNT',
                'SOLOPMT_REF',
                'SOLOPMT_DATE',
                'SOLOPMT_CUR'
            ),
            // response to the request to make a transaction
            'payment_response' => array(
                'SOLOPMT_RETURN_VERSION',
                'SOLOPMT_RETURN_STAMP',
                'SOLOPMT_RETURN_REF',
                'SOLOPMT_RETURN_PAID'
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function loadTransactionFromResponse(Response $response, Transaction $transaction)
    {
        /** @noinspection SpellCheckingInspection */
        {
            $transaction->setTransactionID($response->getParam('SOLOPMT_RETURN_STAMP', false));
            $transaction->setReference($response->getParam('SOLOPMT_RETURN_REF', false));
            $transaction->setSum($response->getParam('SOLOPMT_RETURN_AMOUNT', false));
            $transaction->setComment($response->getParam('SOLOPMT_RETURN_MSG', false));
            $transaction->setCurrency($response->getParam('SOLOPMT_RETURN_CUR', false));
            $transaction->setLanguage($response->getParam('SOLOPMT_RETURN_LANGUAGE', false));
        }
    }

    /**
     * @inheritdoc
     */
    public function canHandlePaymentResponse(Response $response)
    {
        /** @noinspection SpellCheckingInspection */
        return $response->hasParam('SOLOPMT_RETURN_MAC');
    }

    /**
     * @inheritdoc
     */
    public function handlePaymentResponse(Response $response)
    {
        $this->verifyResponseMac($response);
        /** @noinspection SpellCheckingInspection */
        $isSuccessful = $response->hasParam('SOLOPMT_RETURN_PAID');
        $response->setIsSuccessful($isSuccessful);
    }

    /**
     * Verifies if a given MAC in a response object is valid
     *
     * @param Response $response
     * @throws \dlds\payment\Exception
     */
    public function verifyResponseMac(Response $response)
    {
        $mac = $this->generateMacKey($response, 'payment_response');

        /** @noinspection SpellCheckingInspection */
        if ($mac !== $response->getParam('SOLOPMT_RETURN_MAC')) {
            /** @noinspection SpellCheckingInspection */
            $params = [
                'SOLOPMT_RETURN_VERSION' => $response->getParam('SOLOPMT_RETURN_VERSION'),
                'SOLOPMT_RETURN_STAMP' => $response->getParam('SOLOPMT_RETURN_STAMP'),
                'SOLOPMT_RETURN_REF' => $response->getParam('SOLOPMT_RETURN_REF'),
                'SOLOPMT_RETURN_PAID' => $response->getParam('SOLOPMT_RETURN_PAID'),
                'SOLOPMT_RETURN_MAC' => $response->getParam('SOLOPMT_RETURN_MAC'),
                'generated_mac' => $mac,
            ];
            throw new Exception('BankNet-> Incorrect signature. (' . implode(',', $params) . ')');
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateConf()
    {
        parent::validateConf();
        if (false !== ($function = $this->getConfParam('hash_function', false))) {
            if (!in_array($function, $this->hashFunctions)) {
                throw new Exception("Unknown hash function: '{$function}'. Please use one of: " . implode(
                        ', ',
                        $this->hashFunctions
                    ));
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function getParamDefinition()
    {
        /** @noinspection SpellCheckingInspection */
        return array(
            'SOLOPMT_VERSION' => array('length' => 4),
            'SOLOPMT_STAMP' => array('length' => 20),
            'SOLOPMT_RCV_ID' => array('length' => 15),
            'SOLOPMT_RCV_ACCOUNT' => array('length' => 21),
            'SOLOPMT_RCV_NAME' => array('length' => 30),
            'SOLOPMT_LANGUAGE' => array('length' => 1),
            'SOLOPMT_AMOUNT' => array('length' => 19),
            'SOLOPMT_REF' => array('length' => 16),
            'SOLOPMT_TAX_CODE' => array('length' => 28),
            'SOLOPMT_DATE' => array('length' => 10),
            'SOLOPMT_MSG' => array('length' => 210),
            'SOLOPMT_RETURN' => array('length' => 256),
            'SOLOPMT_CANCEL' => array('length' => 256),
            'SOLOPMT_REJECT' => array('length' => 256),
            'SOLOPMT_MAC' => array('length' => 32),
            'SOLOPMT_CONFIRM' => array('length' => 3),
            'SOLOPMT_KEYVERS' => array('length' => 4),
            'SOLOPMT_CUR' => array('length' => 3),
            'SOLOPMT_RETURN_VERSION' => array('length' => 4), // payment_response only
            'SOLOPMT_RETURN_STAMP' => array('length' => 20), // payment_response only
            'SOLOPMT_RETURN_REF' => array('length' => 16), // payment_response only
            'SOLOPMT_RETURN_PAID' => array('length' => 24), // payment_response only
        );
    }
}