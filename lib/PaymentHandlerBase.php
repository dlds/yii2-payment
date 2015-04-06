<?php /** @noinspection SpellCheckingInspection */
/**
 * Extendable PaymentHandler base class that provides payment functionality.
 * Payment adapters supported so far:
 *    - IPizza (seb, swedbank, danske, krediidipank)
 *    - Nordea
 *    - Estcard (credit card payments)
 *
 * Charsets supported:
 * - UTF-8
 *        - krediidipank
 *        - swedbank
 * - ISO-5589-1 (with transliteration)
 *        - seb (utf-8 may be possible, but not implemented)
 *    - danske (utf-8 not supported by API)
 *    - lhv (utf-8 not supported by API)
 *    - nordea (utf-8 not supported by API)
 *    - estcard (utf-8 not supported by API)
 *
 * @see https://github.com/opus-online/yii2-payment/ for examples
 * @date 19.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */

namespace dlds\payment;

use dlds\payment\adapters\AbstractAdapter;
use dlds\payment\services\Payment;
use dlds\payment\services\payment\Form;
use yii\base\Component;
use yii\base\Object;
use yii\helpers\ArrayHelper;
use yii\web\Application;

/**
 * Class PaymentHandlerBase
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment
 */
abstract class PaymentHandlerBase extends Object
{
    /** @var string Estonian language */
    const LANGUAGE_ET = 'et';
    /** @var string Russian language */
    const LANGUAGE_RU = 'ru';
    /** @var string English language */
    const LANGUAGE_EN = 'en';

    /** @var string Default currency to use with transactions */
    const DEFAULT_CURRENCY = 'EUR';
    /** @var string Defines default language to use when one is not specified */
    const DEFAULT_LANGUAGE = self::LANGUAGE_ET;

    /** @var string Payment service type */
    const SERVICE_PAYMENT = 'payment';
    /** @var string Authentication service type (not implemented yet) */
    const SERVICE_AUTHENTICATION = 'authentication';

    /** @var array Array of configuration parameters */
    protected $conf;
    /** @var array Holds the array of adapter objects */
    protected $adapters;
    /**
     * @var Application
     */
    public $app;

    /**
     * Create a new PaymentHandler object
     */
    public function __construct($params = [])
    {
        parent::__construct($params);
        $this->app = \Yii::$app;
    }

    /**
     * Returns the configuration array
     */
    abstract public function getConfiguration();

    /**
     * Initialize all enabled adapters (specified in the configuration)
     *
     * @throws Exception
     */
    public function init()
    {
        $this->conf = $this->getConfiguration();
        if (!isset($this->conf)) {
            throw new Exception("Adapter configuration not set, cannot initialize");
        }
        $this->adapters = array();
        foreach ($this->conf['adapters'] as $key => $adapter) {
            $adapterConf = ArrayHelper::merge($this->conf['common'], $adapter);

            $adapterConf['paymentHandler'] = $this;
            $adapterConf['adapterTag'] = $key;
            $this->adapters[$key] = \Yii::createObject($adapterConf);
        }
    }

    /**
     * Returns an array of usable adapters
     *
     * @return array
     * @throws Exception
     */
    public function getAdapters()
    {
        if (!isset($this->adapters)) {
            throw new Exception("Adapters not loaded yet.");
        }

        return $this->adapters;
    }

    /**
     * Create and return a service handler object.
     *
     * @param string $serviceType Possible options: PaymentHandlerBase::SERVICE_PAYMENT
     * @return mixed
     * @throws Exception
     */
    public function createService($serviceType)
    {
        $class = sprintf('%s\services\%s', __NAMESPACE__, ucwords($serviceType));
        if (class_exists($class)) {
            $handler = new $class($this);
            return $handler;
        } else {
            throw new Exception("Unknown service: $serviceType");
        }
    }

    /**
     * @return Payment
     */
    public static function createPaymentService()
    {
        $class = get_called_class();
        $handler = new $class;
        /** @var $handler self */
        return $handler->createService(self::SERVICE_PAYMENT);
    }

    /**
     * Format configuration parameters right before usage.
     *
     * @param string $param
     * @param mixed $value
     * @return mixed
     */
    public function formatConfigParam($param, $value)
    {
        return $value;
    }

    /**
     * Finalize a FORM (add custom elements, etc)
     *
     * @param Form $form
     * @param AbstractAdapter $adapter Adapter associated with this form
     */
    public function finalizeForm(Form $form, AbstractAdapter $adapter)
    {
        // default behaviour does nothing here
    }

    /**
     * Overwrite this to add custom file path parsing
     * @param string $relativePath
     * @return string
     */
    public function createFilePath($relativePath)
    {
        return $relativePath;
    }

    /**
     * Creates an absolute url from a relative one
     *
     * @param $route
     * @return string
     */
    public function createAbsoluteUrl($route)
    {
        return $this->app->urlManager->createAbsoluteUrl($route);
    }
}
