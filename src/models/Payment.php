<?php

namespace zafarjonovich\Yii2Payment\models;

use Yii;

/**
 * This is the model class for table "yii2_payment".
 *
 * @property int $id
 * @property string $owner_id
 * @property string $transaction
 * @property int|null $code
 * @property int $state
 * @property int $amount
 * @property int|null $reason
 * @property int $time
 * @property int $cancel_time
 * @property int $create_time
 * @property int $perform_time
 */
class Payment extends \yii\db\ActiveRecord
{
    public const STATE_SUCCESS = 2;
    public const STATE_WAITING = 1;
    public const STATE_CANCELED_ON_WAITING = -1;
    public const STATE_CANCELED_ON_SUCCESS = -2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'yii2_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['owner_id', 'transaction', 'state', 'amount'], 'required'],
            [['code', 'state', 'amount', 'reason', 'time', 'cancel_time', 'create_time', 'perform_time'], 'integer'],
            [['owner_id', 'transaction'], 'string', 'max' => 25],
        ];
    }

    /**
     * {@inheritdoc}
     * @return \zafarjonovich\Yii2Payment\models\query\PaymentQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \zafarjonovich\Yii2Payment\models\query\PaymentQuery(get_called_class());
    }

    public function stateIs($state)
    {
        return $this->state == $state;
    }

    public function stateIsWaiting()
    {
        return $this->stateIs(self::STATE_WAITING);
    }

    public function stateIsSuccess()
    {
        return $this->stateIs(self::STATE_SUCCESS);
    }
}
