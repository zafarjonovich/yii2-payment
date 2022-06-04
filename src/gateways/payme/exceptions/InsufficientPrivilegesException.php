<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class InsufficientPrivilegesException extends PaymentException
{
    protected $statusCode = -32504;

    protected $errorMessage = [
        "uz" => "Usulni bajarish uchun imtiyozlar etarli emas.",
        "ru" => "Недостаточно привилегий для выполнения метода",
        "en" => "Insufficient privileges to execute the method"
    ];
}