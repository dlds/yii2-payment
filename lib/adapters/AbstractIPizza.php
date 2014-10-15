<?php /** @noinspection SpellCheckingInspection */

/**
 * Contains common functionality for iPizza-based bank operations (SEB, Swedbank, Danske, Krediidipank)
 *
 * @date 17.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace opus\payment\adapters;

use opus\payment\Exception;
use opus\payment\PaymentHandlerBase;
use opus\payment\services\payment\AdapterInterface;
use opus\payment\services\payment\Dataset;
use opus\payment\services\payment\Response;
use opus\payment\services\payment\Transaction;
use yii\helpers\ArrayHelper;

/**
 * Class AbstractIPizza
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package opus\payment\adapters
 */
class AbstractIPizza extends AbstractAdapter implements AdapterInterface
{
    /**
     * @inheritdoc
     */
    public function getRequiredConfParams()
    {
        return ArrayHelper::merge(
            parent::getRequiredConfParams(),
            array(
                'VK_SND_ID' => 'string',
                'VK_ACC' => 'string',
                'VK_NAME' => 'string',
//                'key_path' => 'string',
//                'cert_path' => 'string',
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function fillPaymentFormDataset(Dataset $dataset, Transaction $transaction)
    {
        $this->addCommonParams($dataset);

        // add params from transaction
        $dataset
            ->setParam('VK_STAMP', $transaction->getTransactionId())
            ->setParam('VK_AMOUNT', $transaction->getSum())
            ->setParam('VK_CURR', $transaction->getCurrency())
            ->setParam('VK_LANG', $this->getLanguageCode($transaction->getLanguage()));

        // add reference if set
        if ($transaction->hasReference()) {
            $dataset->setParam('VK_REF', $transaction->getReference());
        } else {
            if (false !== $this->getConfParam('VK_REF', false)) {
                $dataset->setParam('VK_REF', $this->getConfParam('VK_REF'));
            } else {
                $dataset->setParam('VK_REF', null);
            }
        }

        // add comment if set
        if ($transaction->hasComment()) {
            $dataset->setParam('VK_MSG', $transaction->getComment());
        } else {
            if (false !== $this->getConfParam('VK_MSG', false)) {
                $dataset->setParam('VK_MSG', $this->getConfParam('VK_MSG'));
            }
        }

        $this->formatParams($dataset);
        $this->addMacSignature($dataset);
    }

    /**
     * This can be overridden by child classes of iPizza if necessary
     */
    public function addCommonParams(Dataset $dataset)
    {
        $dataset
            ->setParam('VK_SERVICE', $this->getConfParam('VK_SERVICE', '1001'))
            ->setParam('VK_VERSION', '008')
            ->setParam('VK_SND_ID', $this->getConfParam('VK_SND_ID'))
            ->setParam('VK_ACC', $this->getConfParam('VK_ACC'))
            ->setParam('VK_NAME', $this->getConfParam('VK_NAME'))
            ->setParam('VK_RETURN', $this->getReturnUrl())
            ->setParam('VK_CANCEL', $this->getCancelUrl());
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCode($language)
    {
        switch ($language) {
            case PaymentHandlerBase::LANGUAGE_ET:
                return 'EST';
            case PaymentHandlerBase::LANGUAGE_RU:
                return 'RUS';
            case PaymentHandlerBase::LANGUAGE_EN:
            default:
                return 'ENG';
        }
    }

    /**
     * Generates and adds a MAC signature to the dataset (also adds VK_MAC parameter)
     *
     * @param \opus\payment\services\payment\Dataset $dataset
     * @return bool|string
     */
    private function addMacSignature(Dataset $dataset)
    {
        $macSource = $this->getMacSource($dataset);
        $keyPath = $this->getPkcKeyPath();
        $signature = $this->signWithPrivateKey($keyPath, $macSource);
        $signature = base64_encode($signature);

        $dataset->setParam('VK_MAC', $signature);

        return $signature;
    }

    /**
     * Generates the MAC source string from a dataset
     *
     * @param Dataset $dataset
     * @return string
     */
    private function getMacSource(Dataset $dataset)
    {
        $macParams = $this->getNormalizedMacParams($dataset, $dataset->getParam('VK_SERVICE'));
        $source = '';

        foreach ($macParams as $value) {
            $length = $this->getParamValueLength($value);
            $source .= str_pad($length, 3, '0', STR_PAD_LEFT) . $value;
        }

        return $source;
    }

    /**
     * @inheritdoc
     */
    public function handlePaymentResponse(Response $response)
    {
        if ($this->verifyResponseMac($response)) {
            switch ($response->getParam('VK_SERVICE', '')) {
                case '1101':
                    $response->setIsSuccessful(true);
                    break;
                case '1902':
//					throw new \CException('aaa');
                case '1901':
                    // transaction did not succeed
                    break;
                default:
                    throw new Exception("Transaction returned unknown VK_SERVICE code");
            }
            if ($response->getParam('VK_AUTO', false) === 'Y') {
                $response->setIsAutomatic(true);
            } else {
                if ($response->getParam('VK_AUTO', false) === 'N') {
                    $response->setIsAutomatic(false);
                }
            }
        }
    }

    /**
     * Verifies if a given MAC in a response object is valid
     *
     * @param Response $response
     * @return bool
     */
    private function verifyResponseMac(Response $response)
    {
        $macSource = $this->getMacSource($response);
        $macValue = $response->getParam('VK_MAC');
        $certPath = $this->getPkcCertPath();

        $isVerified = $this->verifySignatureWithCertificate($certPath, $macSource, base64_decode($macValue));

        return (bool)$isVerified;
    }

    /**
     * @inheritdoc
     */
    public function loadTransactionFromResponse(Response $response, Transaction $transaction)
    {
        $transaction->setTransactionID($response->getParam('VK_STAMP', false));
        $transaction->setReference($response->getParam('VK_REF', false));
        $transaction->setSum($response->getParam('VK_AMOUNT', false));
        $transaction->setComment($response->getParam('VK_MSG', false));
        $transaction->setCurrency($response->getParam('VK_CURR', false));
        $transaction->setLanguage($response->getParam('VK_LANG', false));
    }

    /**
     * @inheritdoc
     */
    public function canHandlePaymentResponse(Response $response)
    {
        return $response->hasParam('VK_SERVICE');
    }

    /**
     * @inheritdoc
     */
    protected function getParamDefinition()
    {
        return array(
            'VK_SERVICE' => array('length' => 4),
            'VK_VERSION' => array('length' => 3),
            'VK_SND_ID' => array('length' => 15),
            'VK_REC_ID' => array('length' => 15),
            'VK_STAMP' => array('length' => 20),
            'VK_T_NO' => array('length' => 5), // 1101 only
            'VK_AMOUNT' => array('length' => 17),
            'VK_CURR' => array('length' => 3),
            'VK_ACC' => array('length' => 16),
            'VK_REC_ACC' => array('length' => 16), // 1101 only
            'VK_NAME' => array('length' => 40),
            'VK_REC_NAME' => array('length' => 40), // 1101 only
            'VK_SND_ACC' => array('length' => 16), // 1101 only
            'VK_SND_NAME' => array('length' => 40), // 1101 only
            'VK_REF' => array('length' => 20),
            'VK_MSG' => array('length' => 70),
            'VK_T_DATE' => array('length' => 10), // 1101 only
            'VK_MAC' => array('length' => 344),
            'VK_RETURN' => array('length' => 60),
            'VK_CANCEL' => array('length' => 60),
            'VK_LANG' => array('length' => 3),
            'VK_CHARSET' => array('length' => 10),
        );
    }

    /**
     * @inheritdoc
     */
    protected function getParamMacOrderDefinition()
    {
        return array(
            // request to make a transaction
            '1001' => array(
                'VK_SERVICE',
                'VK_VERSION',
                'VK_SND_ID',
                'VK_STAMP',
                'VK_AMOUNT',
                'VK_CURR',
                'VK_ACC',
                'VK_NAME',
                'VK_REF',
                'VK_MSG'
            ),
            // request to make a transaction
            '1002' => array(
                'VK_SERVICE',
                'VK_VERSION',
                'VK_SND_ID',
                'VK_STAMP',
                'VK_AMOUNT',
                'VK_CURR',
                'VK_REF',
                'VK_MSG'
            ),
            // 'transaction completed' response message
            '1101' => array(
                'VK_SERVICE',
                'VK_VERSION',
                'VK_SND_ID',
                'VK_REC_ID',
                'VK_STAMP',
                'VK_T_NO',
                'VK_AMOUNT',
                'VK_CURR',
                'VK_REC_ACC',
                'VK_REC_NAME',
                'VK_SND_ACC',
                'VK_SND_NAME',
                'VK_REF',
                'VK_MSG',
                'VK_T_DATE'
            ),
            // 'transaction not completed' response message
            '1901' => array(
                'VK_SERVICE',
                'VK_VERSION',
                'VK_SND_ID',
                'VK_REC_ID',
                'VK_STAMP',
                'VK_REF',
                'VK_MSG'
            ),
        );
    }
}
