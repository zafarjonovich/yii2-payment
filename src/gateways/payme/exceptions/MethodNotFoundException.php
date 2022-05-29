<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class MethodNotFoundException extends PaymentException
{
    protected $statusCode = -32601;

    protected $errorMessage = [
        "uz" => "Metod topilmadi",
        "ru" => "Запрашиваемый метод не найден.",
        "en" => "Method not found"
    ];
}