<?php

namespace zafarjonovich\Yii2Payment\gateways\payme\controllers;

use yii\base\DynamicModel;
use yii\filters\VerbFilter;
use yii\helpers\VarDumper;
use yii\validators\RequiredValidator;
use yii\validators\SafeValidator;
use yii\web\Response;
use zafarjonovich\Yii2Payment\base\GatewayController;
use zafarjonovich\Yii2Payment\gateways\payme\exceptions\CanNotPerformTransactionException;
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

    public function init()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->request->parsers['application/json'] = 'yii\web\JsonParser';
        $this->enableCsrfValidation = false;

        \Yii::$app->response->on(
            Response::EVENT_BEFORE_SEND,
            function ($event) {
                /**
                 * @var yii\base\Event $event
                 * @var \yii\web\Response $response
                 */
                $response = $event->sender;

                $exception = \Yii::$app->errorHandler->exception;

                if (null !== $exception) {
                    if (!($exception instanceof PaymentException)) {
                        $exception = new PaymentException($exception->getMessage());
                    }
                    $response->data = [
                        'error' => [
                            "code" => $exception->getStatusCode(),
                            "message" => $exception->getErrorMessages(),
                            "data" => []
                        ],
                        'result' => null,
                        'id' => $this->apiRequest->getParams()->hasAttribute('id') ? $this->apiRequest->getParams()->getId() : null
                    ];
                }
            }
        );

        parent::init();
    }

    public function actionHook()
    {
        $request = Request::load();
        $methodName = "action{$request->getMethod()}";

        if (!$this->hasMethod($methodName)) {

            throw new MethodNotFoundException("{$request->getMethod()} method not found");
        }

        $this->apiRequest = $request;
        return $this->{$methodName}();
    }

    public function actionCheckPerformTransaction()
    {
        $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

        $amount = $this->apiRequest->getParams()->getAmount();

        if ($this->minAmount && $this->minAmount > $amount) {
            throw new WrongAmountException();
        }

        if ($this->maxAmount && $this->maxAmount < $amount) {
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
            "error" => null,
            "result" => [
                'create_time' => $payment->create_time,
                'transaction' => $payment->id,
                'state' => $payment->state,
            ],
            "id" => $this->apiRequest->getParams()->getId()
        ];
    }

    public function actionPerformTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();
        $amount = $this->apiRequest->getParams()->getAmount();
        $ornerId = $this->getOwnerIdByAccount(
            $this->apiRequest->getParams()->getAccount()
        );

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
        } else if (!$payment->stateIsSuccess()) {
            throw new CanNotPerformTransactionException();
        }

        return [
            "error" => null,
            "result" => [
                "state" => $payment->state,
                "perform_time" => $payment->perform_time,
                "transaction" => $payment->id
            ],
            "id" => $this->apiRequest->getParams()->getId()
        ];
    }

    public function actionCancelTransaction()
    {
        $transactionId = $this->apiRequest->getParams()->getId();
        $reason = $this->apiRequest->getParams()->getReason();

        $payment = Payment::findOne(['transaction' => $transactionId]);

        if (null === $payment) {
            throw new TransactionNotFoundException();
        }

        $payment->cancel_time = time() * 1000;

        if ($payment->stateIsWaiting()) {
            $payment->state = Payment::STATE_CANCELED_ON_WAITING;
        }

        if ($payment->stateIsSuccess()) {
            $payment->state = Payment::STATE_CANCELED_ON_SUCCESS;
        }

        $payment->save(false);

        return [
            "error" => null,
            "result" => [
                "state" => $payment->state,
                "cancel_time" => $payment->cancel_time,
                "transaction" => $payment->id
            ],
            "id" => $this->apiRequest->getParams()->getId()
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
            "error" => null,
            "result" => [
                "create_time" => $payment->create_time,
                "perform_time" => $payment->perform_time,
                "cancel_time" => $payment->cancel_time,
                "transaction" => $payment->id,
                "state" => $payment->state,
                "reason" => $payment->reason
            ],
            "id" => $this->apiRequest->getParams()->getId()
        ];
    }

    public function actionGetStatement()
    {
    }
}