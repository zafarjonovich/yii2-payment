<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class WaitingPaymentAlreadyExistsException extends PaymentException
{
    protected $statusCode = -31050;

    protected $errorMessage = [
        "uz" => "To'lov qilish kutilayotgan tranzaksiya mavjud",
        "ru" => "Есть транзакция, ожидающая оплаты",
        "en" => "There is a transaction that is expected to pay",
    ];
}