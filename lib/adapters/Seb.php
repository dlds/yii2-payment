<?php /** @noinspection SpellCheckingInspection */

/**
 * Handles payment actions for SEB.
 * Based on iPizza platform.
 *
 * Specification: http://www.seb.ee/ari/maksete-kogumine/maksete-kogumine-internetis/tehniline-spetsifikatsioon
 *
 * @todo UTF-8 support
 * @date 17.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\adapters;

use dlds\payment\services\payment\Dataset;
use dlds\payment\services\payment\Response;
use yii\helpers\ArrayHelper;

/**
 * Class Seb
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\adapters
 */
class Seb extends AbstractIPizza
{
    /**
     * @inheritdoc
     */
    public $name = 'SEB';
    /**
     * @inheritdoc
     */
    public $charset = 'ISO-8859-1';

    /**
     * @inheritdoc
     */
    public function canHandlePaymentResponse(Response $response)
    {
        $isSEB = 'EYP' == $response->getParam('VK_SND_ID', false);

        return $isSEB && parent::canHandlePaymentResponse($response);
    }

    /**
     * @inheritdoc
     */
    public function getParamMacOrderDefinition()
    {
        return ArrayHelper::merge(
            parent::getParamMacOrderDefinition(),
            array(
                // 'transaction not completed' response with error message
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
                // 'technical difficulties' response message
                '1902' => array(
                    'VK_SERVICE',
                    'VK_VERSION',
                    'VK_SND_ID',
                    'VK_REC_ID',
                    'VK_STAMP',
                    'VK_REF',
                    'VK_MSG',
                    'VK_ERROR_CODE'
                ),
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function addCommonParams(Dataset $dataset)
    {
        parent::addCommonParams($dataset);
        $dataset->setParam('VK_CHARSET', $this->getCharset());
    }
}