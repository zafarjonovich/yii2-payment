<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class TransactionNotFoundException extends PaymentException
{
    protected $statusCode = -31003;

    protected $errorMessage = [
        "en" => "Transaction not found",
        "ru" => "Трансакция не найдена",
        "uz" => "Transaksiya topilmadi"
    ];
}