<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class AccountNotFoundException extends PaymentException
{
    protected $statusCode = -31050;

    protected $errorMessage = [
        "uz" => "Foydalanuvchi topilmadi",
        "ru" => "Пользователь не найден",
        "en" => "User not found",
    ];
}