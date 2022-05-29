<?php

namespace zafarjonovich\Yii2Payment\base;

use yii\web\Controller;

abstract class GatewayController extends Controller
{
    abstract public function actionCheckPerformTransaction();

    abstract public function actionCreateTransaction();

    abstract public function actionPerformTransaction();

    abstract public function actionCancelTransaction();

    abstract public function actionCheckTransaction();

    abstract public function actionGetStatement();
}