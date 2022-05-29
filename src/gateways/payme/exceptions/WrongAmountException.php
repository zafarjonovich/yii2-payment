<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class WrongAmountException extends PaymentException
{
    protected $statusCode = -31001;

    protected $errorMessage = [
        "uz" => "Notug'ri summa.",
        "ru" => "Неверная сумма.",
        "en" => "Wrong amount.",
    ];
}