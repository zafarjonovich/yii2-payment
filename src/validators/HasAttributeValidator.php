<?php

namespace zafarjonovich\Yii2Payment\validators;

use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\validators\Validator;
use zafarjonovich\Yii2Payment\base\exceptions\RequestParseException;

class HasAttributeValidator extends Validator
{
    public $paths = [];

    protected function validateValue($value)
    {
        $defaultValue = \Yii::$app->security->generateRandomString(64);
        foreach ($this->paths as $path) {
            if (ArrayHelper::getValue($value,$path,$defaultValue) !== $defaultValue) {
                throw new RequestParseException($this->paths);
            }
        }

        return [];
    }
}