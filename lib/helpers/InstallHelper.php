<?php
/**
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @date 28.01.14
 */

namespace dlds\payment\helpers;

use yii\base\InvalidParamException;

/**
 * Class InstallHelper
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\helpers
 */
class InstallHelper
{
    const CONF_DEFAULT = 'banks-default.php';
    const CONF_LOCAL = 'banks-local.php';

    /**
     * @param string $type
     * @return string
     * @throws \yii\base\InvalidParamException
     */
    public static function getExampleConfiguration($type)
    {
        if (in_array($type, [self::CONF_DEFAULT, self::CONF_LOCAL])) {
            $path = realpath(__DIR__ . '/../../examples/config/' . $type);
            return file_get_contents($path);
        }
        throw new InvalidParamException('Invalid configuration file');
    }

    /**
     * Writes a default configuration file to $path if there is none
     * @param string $path
     * @param string $type
     */
    public static function ensureConfigFile($path, $type)
    {
        if (!is_file($path)) {
            $content = self::getExampleConfiguration($type);
            file_put_contents($path, $content);
        }
    }
} 