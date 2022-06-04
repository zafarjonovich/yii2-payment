<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\base;

use yii\helpers\ArrayHelper;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\PaymentException;

class Credentials
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
            throw new PaymentException("$key not found in request");
        }

        return $value;
    }

    public function getLogin()
    {
        return $this->get('login');
    }

    public function getPassword()
    {
        return $this->get('password');
    }
}