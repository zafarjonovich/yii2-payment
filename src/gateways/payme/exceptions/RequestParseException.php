<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

class RequestParseException extends PaymentException
{
    protected $statusCode = -32700;

    protected $errorMessage = [
        "uz" => "So'rovdagi attributlar yetishmayapti",
        "ru" => "Атрибуты в запросе отсутствуют",
        "en" => "The attributes in the query are missing"
    ];
}