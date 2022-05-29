<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class PaymentException extends \zafarjonovich\Yii2Payment\base\exceptions\PaymentException
{
    protected $statusCode = -32400;

    protected $errorMessage = [
        "uz" => "Ichki sestema hatoligi",
        "ru" => "Внутренняя ошибка сервера",
        "en" => "Internal server error"
    ];

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getErrorMessages()
    {
        return $this->errorMessage;
    }
}