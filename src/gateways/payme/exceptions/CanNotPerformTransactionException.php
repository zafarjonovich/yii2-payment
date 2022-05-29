<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\exceptions;

use JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;

class CanNotPerformTransactionException extends PaymentException
{
    protected $statusCode = -31008;

    protected $errorMessage = [
        "uz" => "Bu operatsiyani bajarish mumkin emas",
        "ru" => "Невозможно выполнить данную операцию.",
        "en" => "Can't perform transaction",
    ];
}