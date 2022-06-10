<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\controllers;

use Codeception\Util\HttpCode;
use yii\base\DynamicModel;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBasicAuth;
use yii\filters\VerbFilter;
use yii\helpers\VarDumper;
use yii\validators\RequiredValidator;
use yii\validators\SafeValidator;
use yii\web\Response;
use zafarjonovich\Yii2Payment\base\GatewayController;
use zafarjonovich\Yii2Payment\gateways\gateways\payme\actions\ErrorAction;
use zafarjonovich\Yii2Payment\gateways\payme\base\Credential;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\CanNotPerformTransactionException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\InsufficientPrivilegesException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\MethodNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\ParseException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\PaymentException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\TransactionNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\WrongAmountException;
use zafarjonovich\Yii2Payment\models\Payment;
use zafarjonovich\Yii2Payment\validators\HasAttributeValidator;
use zafarjonovich\Yii2Payment\gateways\payme\base\Request;

class Controller extends GatewayController
{
    protected Request $apiRequest;

    protected $minAmount;

    protected $maxAmount;

    protected $timeout;

    /**
     * @param $account
     * @return mixed
     * @throws RequestParseException
     */
    public function getOwnerIdByAccount($account)
    {
        throw new RequestParseException('Account method must set');
    }

    /**
     * @return Credential
     * @throws PaymentException
     */
    public function getCredentials()
    {
        throw new PaymentException('Credentials not found');
    }

    public function init()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->request->parsers['application/json'] = 'yii\web\JsonParser';
        $this->enableCsrfValidation = false;

        \Yii::$app->response->on(
            Response::EVENT_BEFORE_SEND,
            function ($event) {

                $response = $event->sender;

                $response->statusCode = 200;

                $exception = \Yii::$app->errorHandler->exception;

                $result = $response->data;
                $error = null;

                if (null !== $exception) {
                    if (!($exception instanceof PaymentException)) {
                        $exception = new PaymentException($exception->getMessage());
                    }

                    $error = [
                        "code" => $exception->getStatusCode(),
                        "message" => $exception->getErrorMessages(),
                        "data" => []
                    ];

                    $result = null;
                }

                $response->data = [
                    'error' => $error,
                    'result' => $result,
                    'id' => $this->apiRequest->getParams()->hasAttribute('id') ? $this->apiRequest->getParams()->getId() : null
                ];

            }
        );

        parent::init();
    }

    public function checkPermission()
    {
        $authorization = $this->request->getHeaders()->get('authorization');

        if (null === $authorization) {
            throw new InsufficientPrivilegesException();
        }

        if (substr($authorization,0,6) != 'Basic ') {
            throw new InsufficientPrivilegesException();
        }

        $base64 = substr($authorization,6);

        $credentials = $this->getCredentials();

        if ($base64 != base64_encode("{$credentials->getLogin()}:{$credentials->getPassword()}")) {
            throw new InsufficientPrivilegesException();
        }
    }

    public function actionHook()
    {
        $request = Request::load();
        $this->apiRequest = $request;

        $methodName = "action{$request->getMethod()}";

        if (!$this->hasMethod($methodName)) {
            throw new MethodNotFoundException("{$request->getMethod()} method not found");
        }

        $this->checkPermission();

        return $this->{$methodName}();
    }

    /**
     * @param $account
     * @param $amount
     * @return bool
     */
    protected function checkPerformTransaction($account,$amount)
    {
        return true;
    }

    public function actionCheckPerformTransaction()
    {
        $account = $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

        $amount = $this->apiRequest->getParams()->getAmount();

        if ($this->minAmount && $this->minAmount > $amount) {
            throw new WrongAmountException();
        }

        if ($this->maxAmount && $this->maxAmount < $amount) {
            throw new WrongAmountException();
        }

        if (!$this->checkPerformTransaction($account,$amount)) {
            throw new WrongAmountException();
        }

        return [
            "allow" => true
        ];
    }

    public function actionCreateTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();
        $amount = $this->apiRequest->getParams()->getAmount();
        $ornerId = $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

        if (!$this->checkPerformTransaction($ornerId,$amount)) {
            throw new WrongAmountException();
        }

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if (null !== $payment) {
            if (!$payment->stateIsWaiting()) {
                throw new CanNotPerformTransactionException('Transaction is canceled');
            }
        } else {
            $payment = new Payment([
                'transaction' => $transactionId,
                'time' => $this->apiRequest->getParams()->getTime(),
                'amount' => $amount,
                'state' => Payment::STATE_WAITING,
                'create_time' => time() * 1000,
                'owner_id' => $ornerId,
            ]);

            $payment->save(false);
        }

        return [
            'create_time' => (int)$payment->create_time,
            'transaction' => (string)$payment->id,
            'state' => (int)$payment->state,
        ];
    }

    /**
     * @param $ownerId
     * @return void
     */
    protected function performTransaction($ownerId)
    {
    }

    public function actionPerformTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if (null === $payment) {
            throw new TransactionNotFoundException();
        }

        if ($this->timeout && time() * 1000 > $payment->create_time + $this->timeout) {
            throw new CanNotPerformTransactionException();
        }

        if ($payment->stateIsWaiting()) {
            $payment->perform_time = time() * 1000;
            $payment->state = Payment::STATE_SUCCESS;
            $payment->save(false);

            $this->performTransaction($payment->owner_id);
        } else if (!$payment->stateIsSuccess()) {
            throw new CanNotPerformTransactionException();
        }

        return [
            "state" => (int)$payment->state,
            "perform_time" => (int)$payment->perform_time,
            "transaction" => (string)$payment->id
        ];
    }

    /**
     * @param $ownerId
     * @return void
     */
    protected function cancelTransaction($ownerId)
    {
    }

    public function actionCancelTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if (null === $payment) {
            throw new TransactionNotFoundException();
        }

        $oldState = $payment->state;

        $payment->cancel_time = time() * 1000;
        $payment->reason = $this->apiRequest->getParams()->getReason();

        if ($payment->stateIsWaiting()) {
            $payment->state = Payment::STATE_CANCELED_ON_WAITING;
        }

        if ($payment->stateIsSuccess()) {
            $payment->state = Payment::STATE_CANCELED_ON_SUCCESS;
        }

        $payment->save(false);

        if ($oldState != $payment->state) {
            $this->cancelTransaction($payment->owner_id);
        }

        return [
            "state" => (int)$payment->state,
            "cancel_time" => (int)$payment->cancel_time,
            "transaction" => (string)$payment->id
        ];
    }

    public function actionCheckTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if (null === $payment) {
            throw new TransactionNotFoundException();
        }

        return [
            "create_time" =>(int) $payment->create_time,
            "perform_time" => (int)$payment->perform_time,
            "cancel_time" => (int)$payment->cancel_time,
            "transaction" => (string)$payment->id,
            "state" => (int)$payment->state,
            "reason" => $payment->reason
        ];
    }

    public function actionGetStatement()
    {
    }

    public function actionChangePassword()
    {
    }
}