<?php
use dlds\payment\helpers\InstallHelper;

/**
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @date 21.01.14
 */
class PaymentHandler extends \dlds\payment\PaymentHandlerBase
{
    public function init()
    {
        InstallHelper::ensureConfigFile('banks-default.php', InstallHelper::CONF_DEFAULT);
        InstallHelper::ensureConfigFile('banks-local.php', InstallHelper::CONF_LOCAL);
        parent::init();
    }

    /**
     * Returns the configuration array
     */
    public function getConfiguration()
    {
        return [
            'common' => array(
                'returnRoute' => '/ret.php',
                'params' => [],
            ),
            'adapters' => \yii\helpers\ArrayHelper::merge(require 'banks-default.php', require 'banks-local.php'),
        ];
    }

    /**
     * Overwrite this to add custom file path parsing
     * @param string $relativePath
     * @return string
     */
    public function createFilePath($relativePath)
    {
        return 'keys/' . $relativePath;
    }
}
