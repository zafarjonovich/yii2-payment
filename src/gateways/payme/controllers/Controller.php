<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\controllers;


use yii\web\Response;
use zafarjonovich\Yii2Payment\base\GatewayController;
use zafarjonovich\Yii2Payment\gateways\payme\base\Credential;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\CanNotPerformTransactionException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\InsufficientPrivilegesException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\MethodNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\ParseException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\PaymentException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\RequestParseException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\TransactionNotFoundException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\WaitingPaymentAlreadyExistsException;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\WrongAmountException;
use zafarjonovich\Yii2Payment\models\Payment;
use zafarjonovich\Yii2Payment\gateways\payme\base\Request;

class Controller extends GatewayController
{
    protected Request $apiRequest;

    protected $minAmount;

    protected $maxAmount;

    protected $timeout;

    public function getOwnerIdByAccount($account)
    {
        throw new RequestParseException('Account method must set');
    }

    public function getCredentials()
    {
        throw new PaymentException('Credentials not found');
    }

    public function beforeAction($action)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->request->parsers['application/json'] = 'yii\web\JsonParser';
        $this->enableCsrfValidation = false;

        $request = Request::load();
        $this->apiRequest = $request;

        $this->checkPermission();

        return parent::beforeAction($action);
    }

    /**
     * @throws InsufficientPrivilegesException
     * @throws PaymentException
     */
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
        $request = $this->apiRequest;

        $methodName = "action{$request->getMethod()}";

        if (!$this->hasMethod($methodName)) {
            throw new MethodNotFoundException("{$request->getMethod()} method not found");
        }

        $requestId = $request->getParams()->hasAttribute('id') ? $request->getParams()->getId() : null;

        try {
            return [
                'error' => null,
                'result' => $this->{$methodName}(),
                'id' => $requestId
            ];
        } catch (PaymentException $exception) {
            return [
                'error' => [
                    "code" => $exception->getStatusCode(),
                    "message" => $exception->getErrorMessages(),
                    "data" => []
                ],
                'result' => null,
                'id' => $requestId
            ];
        }
    }

    protected function checkPerformTransaction($ownerId,$amount)
    {
        return true;
    }

    public function actionCheckPerformTransaction()
    {
        $ownerId = $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

        $amount = $this->apiRequest->getParams()->getAmount();

        if ($this->minAmount && $this->minAmount > $amount) {
            throw new WrongAmountException();
        }

        if ($this->maxAmount && $this->maxAmount < $amount) {
            throw new WrongAmountException();
        }

        if (!$this->checkPerformTransaction($ownerId,$amount)) {
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
        $ownerId = $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

        $existWaitingPayment = Payment::find()
            ->where([
                'owner_id' => $ownerId,
                'state' => Payment::STATE_WAITING,
            ])
            ->andWhere([
                '!=', 'transaction', $transactionId
            ])
            ->exists();

        if ($existWaitingPayment) {
            throw new WaitingPaymentAlreadyExistsException();
        }

        if (!$this->checkPerformTransaction($ownerId,$amount)) {
            throw new WrongAmountException();
        }

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if ($payment !== null) {
            if (!$payment->stateIsWaiting()) {
                throw new CanNotPerformTransactionException('Transaction is canceled/paid');
            }
        } else {
            $payment = new Payment([
                'transaction' => $transactionId,
                'time' => $this->apiRequest->getParams()->getTime(),
                'amount' => $amount,
                'state' => Payment::STATE_WAITING,
                'create_time' => time() * 1000,
                'owner_id' => $ownerId,
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

        $payment = Payment::findOne([
            'transaction' => $transactionId,
        ]);

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

        if ($payment->stateIsWaiting()) {
            $payment->state = Payment::STATE_CANCELED_ON_WAITING;
        }

        if ($payment->stateIsSuccess()) {
            $payment->state = Payment::STATE_CANCELED_ON_SUCCESS;
        }

        if ($oldState != $payment->state) {
            $payment->cancel_time = time() * 1000;
            $payment->reason = $this->apiRequest->getParams()->getReason();
            $this->cancelTransaction($payment->owner_id);
        }

        $payment->save(false);

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
