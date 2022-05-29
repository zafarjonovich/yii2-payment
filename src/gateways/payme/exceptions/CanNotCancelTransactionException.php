<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class CanNotCancelTransactionException extends PaymentException
{
    protected $statusCode = -31007;

    protected $errorMessage = [
        "uz" => "Transaksiyani qayyarib bolmaydi",
        "ru" => "Невозможно отменить транзакцию",
        "en" => "You can not cancel the transaction"
    ];
}