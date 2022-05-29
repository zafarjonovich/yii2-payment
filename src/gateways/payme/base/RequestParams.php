<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\base;

use yii\helpers\ArrayHelper;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;

class RequestParams
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    protected function get($key)
    {
        $value = ArrayHelper::getValue($this->data,$key);

        if (null === $value) {
            throw new RequestParseException("$key not found in request");
        }

        return $value;
    }

    public function hasAttribute($key)
    {
        return array_key_exists($key,$this->data);
    }

    public function getId()
    {
        return $this->get('id');
    }

    public function getAccount()
    {
        return $this->get('account');
    }

    public function getAmount()
    {
        return $this->get('amount');
    }

    public function getTime()
    {
        return $this->get('time');
    }

    public function getReason()
    {
        return $this->get('reason');
    }
}