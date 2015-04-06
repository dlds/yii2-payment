<?php /** @noinspection SpellCheckingInspection */

/**
 * Handles payment actions for Swedbank
 * Specification: https://www.swedbank.ee/static/pdf/business/d2d/paymentcollection/info_banklink_techspec_est.pdf
 * Has full support for UTF-8
 *
 * @date 17.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\adapters;

use dlds\payment\services\payment\Dataset;
use dlds\payment\services\payment\Response;
use yii\helpers\ArrayHelper;

/**
 * Class Swedbank
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\adapters
 */
class Swedbank extends AbstractIPizza
{
    /**
     * @inheritdoc
     */
    public $name = 'Swedbank';

    /**
     * @inheritdoc
     */
    public function getParamDefinition()
    {
        return ArrayHelper::merge(
            parent::getParamDefinition(),
            array(
                'VK_NAME' => array('length' => 30),
                'VK_ENCODING' => array('length' => 10), // length actually not specified
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function canHandlePaymentResponse(Response $response)
    {
        $isSwedbank = 'HP' == $response->getParam('VK_SND_ID', false);

        return $isSwedbank && parent::canHandlePaymentResponse($response);
    }

    /**
     * @inheritdoc
     */
    public function addCommonParams(Dataset $dataset)
    {
        parent::addCommonParams($dataset);
        $dataset->setParam('VK_ENCODING', $this->getCharset());
        $dataset->setParam('VK_SERVICE', $this->getConfParam('VK_SERVICE', '1001'));
    }

    /**
     * @inheritdoc
     */
    public function getParamValueLength($value)
    {
        return mb_strlen($value, $this->getCharset());
    }
}