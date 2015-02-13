<?php

/**
 * Abstract class containing some common functionality for all adapters
 *
 * @date 18.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace opus\payment\adapters;

use opus\payment\Exception;
use opus\payment\helpers\StringHelper;
use opus\payment\PaymentHandlerBase;
use opus\payment\services\payment\Dataset;
use yii\base\Object;

/**
 * Class AdaptersAbstract
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package opus\payment\adapters
 */
abstract class AbstractAdapter extends Object
{
    /**
     * Default charset to use when one is not defined in the adapter
     */
    const DEFAULT_CHARSET = 'utf-8';
    /**
     * Locale to use when one is not defined in the adapter. This is mainly
     * used to transliterate foreign characters
     */
    const DEFAULT_LOCALE = 'et_EE';
    /**
     * @var string Default charset
     */
    public $charset = self::DEFAULT_CHARSET;
    /**
     * @var string Default locale
     */
    public $locale = self::DEFAULT_LOCALE;
    /**
     * @var string Certificate file path
     */
    public $pkcCertPath;
    /**
     * @var string Private key path
     */
    public $pkcKeyPath;

    /**
     * @var string
     */
    public $returnRoute;
    /**
     * @var string
     */
    public $cancelRoute;
    /**
     * @var bool
     */
    public $enabled = true;
    /**
     * @var string URL of the bank gateway
     */
    public $serviceUrl;
    /**
     * @var string Name of the adapter
     */
    public $name = '[adapter name not set]';
    /**
     * @var array Configuration array for this adapter
     */
    public $params;
    /**
     * @var PaymentHandlerBase Reference to the PaymentHandler
     */
    public $paymentHandler;
    /**
     * @var string Short tag of the adapter
     */
    public $adapterTag;

    /**
     * Create a new payment adapter
     *
     * @param array $params
     */
    function __construct(array $params = [])
    {
        parent::__construct($params);
        $this->enabled && $this->validateConf();
    }

    /**
     * Validates adapter configuration variables
     *
     * @throws Exception
     */
    protected function validateConf()
    {
        foreach ($this->getRequiredConfParams() as $name => $type) {
            if (!isset($this->params[$name]) || gettype($this->params[$name]) !== $type) {
                throw new Exception("Parameter '{$name}' missing or not of type '{$type}' (adapter '{$this->name}')");
            }
            if (substr($name, -5) == '_path') {
                $path = $this->getConfParam($name);
                if (!is_file($path)) {
                    throw new Exception("File not found: $path");
                }
            }
        }
    }

    /**
     * Returns an array of configuration parameters (with types) that need
     * to be present for the adapter to be usable
     *
     * @return array
     */
    public function getRequiredConfParams()
    {
        return array();
    }

    /**
     * Returns a configuration parameter
     *
     * @param $param
     * @param mixed $default
     * @return string
     * @throws \opus\payment\Exception
     */
    public function getConfParam($param, $default = null)
    {
        if (isset($this->params[$param])) {
            return $this->paymentHandler->formatConfigParam($param, $this->params[$param]);
        } elseif (isset($default)) {
            return $default;
        } else {
            throw new Exception("Configuration parameter '$param' not set for adapter '" . $this->name . "'");
        }
    }

    /**
     * Getter for pkc key path
     * @return string
     */
    public function getPkcKeyPath()
    {
        return $this->paymentHandler->createFilePath($this->pkcKeyPath);
    }

    /**
     * Getter for pkc key path
     * @return string
     */
    public function getPkcCertPath()
    {
        return $this->paymentHandler->createFilePath($this->pkcCertPath);
    }

    /**
     * Getter for service URL
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->paymentHandler->createAbsoluteUrl($this->returnRoute);
    }

    /**
     * Getter for service URL
     * @return string
     */
    public function getCancelUrl()
    {
        if (null === $this->cancelRoute) {
            $this->cancelRoute = $this->returnRoute;
        }
        return $this->paymentHandler->createAbsoluteUrl($this->returnRoute);
    }

    /**
     * Format all added parameters as described in the parameter definition (cast to string, truncate length)
     *
     * @param Dataset $dataset
     */
    public function formatParams(Dataset $dataset)
    {
        $paramDefinition = $this->getParamDefinition();
        foreach ($dataset as $key => $value) {
            $value = trim($value);
            is_numeric($value) && settype($value, 'string');

            if (isset($paramDefinition[$key])) {
                if ($this->getParamValueLength($value) > $paramDefinition[$key]['length']) {
                    $value = substr($value, 0, $paramDefinition[$key]['length']);
                }
            }

            if (isset($paramDefinition[$key]['mb_str_pad'])) {
                $value = StringHelper::mbStringPad(
                    $value,
                    $paramDefinition[$key]['length'],
                    isset($paramDefinition[$key]['padChar']) ? $paramDefinition[$key]['padChar'] : ' ',
                    $paramDefinition[$key]['pad'],
                    $this->getCharset()
                );
            } elseif (isset($paramDefinition[$key]['format'])) {
                $value = sprintf($paramDefinition[$key]['format'], $value);
            }

            if ($this->getCharset() !== self::DEFAULT_CHARSET) {
                $value = iconv(self::DEFAULT_CHARSET, $this->getCharset() . '//TRANSLIT', $value);
            }

            $dataset->offsetSet($key, $value);

            // remove empty values
            if (!isset($value)) {
                $dataset->offsetUnset($key);
            }
        }
    }

    /**
     * Returns the definition (with max lengths) of MAC parameters
     * Should be overridden by child classes if necessary
     *
     * @return array
     */
    protected function getParamDefinition()
    {
    }

    /**
     * Default implementation for the the function detecting parameter value lengths
     *
     * @param string $value
     * @return int
     */
    public function getParamValueLength($value)
    {
        return strlen($value);
    }

    /**
     * Returns the charset used by this adapter
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * This reorders parameters and removes the ones that are not set (empty values are allowed)
     *
     * @param Dataset $dataset
     * @param string $service What service parameters to return (array key)
     * @return array
     * @throws \opus\payment\Exception
     */
    public function getNormalizedMacParams(Dataset $dataset, $service)
    {
        $params = $this->getParamMacOrderDefinition();
        if (!isset($params[$service])) {
            throw new Exception("Cannot normalize MAC params, unknown service: $service");
        }
        $currentOrder = $params[$service];

        $normalizedMacParams = array();
        // reorder mac params
        foreach ($currentOrder as $param) {
            if ($dataset->offsetExists($param)) {
                $normalizedMacParams[$param] = $dataset->getParam($param);
            }
        }

        return $normalizedMacParams;
    }

    /**
     * Returns the order of MAC parameters for each service type
     * Should be overridden by child classes if necessary
     *
     * @return array
     */
    protected function getParamMacOrderDefinition()
    {
    }

    /**
     * Converts between different language code representations if necessary
     *
     * @param string $language
     * @return string
     */
    public function getLanguageCode($language)
    {
        return strtoupper($language);
    }

    /**
     * Signs a given string with a private key specified by a relative configuration path
     *
     * @param string $keyPath
     * @param string $source
     * @return string
     * @throws \opus\payment\Exception
     */
    protected function signWithPrivateKey($keyPath, $source)
    {
        $file = @fopen($keyPath, 'r');
        if ($file === false) {
            throw new Exception('Could not open private key file. ');
        }

        $privateKey = fread($file, 8192);
        fclose($file);
        $signature = null;

        $key = openssl_pkey_get_private($privateKey);
        $isSignatureCorrect = openssl_sign($source, $signature, $key);
        openssl_free_key($key);

        if ($isSignatureCorrect === false) {
            throw new Exception('Could not sign with private key. ');
        }

        return $signature;
    }

    /**
     * Verifies that the signature is correct for the specified data using a given certificate
     *
     * @param string $certPath
     * @param string $data
     * @param string $signature
     * @return int
     * @throws \opus\payment\Exception
     */
    protected function verifySignatureWithCertificate($certPath, $data, $signature)
    {
        $file = @fopen($certPath, "r");
        if ($file === false) {
            throw new Exception('Could not open certificate file. ');
        }
        $fileSize = filesize($certPath);

        $certificate = fread($file, $fileSize);
        fclose($file);

        $publicKey = openssl_pkey_get_public($certificate);

        $isVerified = openssl_verify($data, $signature, $publicKey);
        openssl_free_key($publicKey);

        if ($isVerified === 0) {
            throw new Exception('Incorrect signature');
        } elseif ($isVerified === -1) {
            throw new Exception('Could not verify signature');
        }

        return $isVerified;
    }
}
