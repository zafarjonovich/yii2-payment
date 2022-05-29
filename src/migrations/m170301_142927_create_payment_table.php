<?php

use yii\db\Migration;

/**
 * Handles the creation of table `payme_uz`.
 */
class m170301_142927_create_payment_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable('yii2_payment', [
            'id' => $this->primaryKey()->unsigned(),
            'owner_id' => $this->string(25)->notNull(),
            'transaction' => $this->string(25)->notNull(),
            'code' => $this->integer(11),
            'state' => $this->integer(2)->notNull(),
            'amount' => $this->integer(11)->notNull(),
            'reason' => $this->integer(3),
            'time' => $this->bigInteger(15)->unsigned()->notNull()->defaultValue(0),
            'cancel_time' => $this->bigInteger(15)->unsigned()->notNull()->defaultValue(0),
            'create_time' => $this->bigInteger(15)->unsigned()->notNull()->defaultValue(0),
            'perform_time' => $this->bigInteger(15)->unsigned()->notNull()->defaultValue(0)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('yii2_payment');
    }
}
