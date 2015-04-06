<?php

/**
 * A simple container of key-value pairs. Used to represent requests, responses,
 * HTML forms etc.
 *
 * @date 19.04.2012
 * @author Ivo Kund <ivo@opus.ee>
 */
namespace dlds\payment\services\payment;

use dlds\payment\adapters\AbstractAdapter;
use yii\base\Arrayable;

/**
 * Class Dataset
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\services\payment
 */
class Dataset implements \Iterator, \ArrayAccess
{
    /**
     * @var AbstractAdapter
     */
    protected $paymentAdapter;
    /** @var array Parameter array for this dataset */
    protected $params;

    /**
     * Create a new instance of a dataset
     *
     * @param array $params
     * @param AbstractAdapter $adapter
     */
    public function __construct(Array $params = array(), AbstractAdapter $adapter = null)
    {
        $this->params = $params;
        $this->paymentAdapter = $adapter;
    }

    /**
     * Returns the adapter reference if it's set
     *
     * @return AbstractAdapter
     */
    public function getAdapter()
    {
        return $this->paymentAdapter;
    }

    /**
     * Set a dataset parameter. Same as self::offsetSet
     *
     * @param string $param
     * @param string $value
     * @return $this
     */
    public function setParam($param, $value)
    {
        $this->offsetSet($param, $value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->params[] = $value;
        } else {
            $this->params[$offset] = $value;
        }
    }

    /**
     * Returns a parameter value. Similar to self::offsetGet
     *
     * @param string $param
     * @param mixed $default
     * @return mixed
     */
    public function getParam($param, $default = null)
    {
        if ($this->offsetExists($param)) {
            return $this->params[$param];
        }

        return $default;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return isset($this->params[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function hasParam($param)
    {
        return $this->offsetExists($param) && !!$this->params[$param];
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        reset($this->params);
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return current($this->params);
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return key($this->params);
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        return next($this->params);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        $key = key($this->params);
        return ($key !== null && $key !== false);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        unset($this->params[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return isset($this->params[$offset]) ? $this->params[$offset] : null;
    }

    /**
     * Converts the object into an array.
     *
     * @return array the array representation of this object
     */
    public function toArray()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return var_export($this->toArray(), true);
    }
}