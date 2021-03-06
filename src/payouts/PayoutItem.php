<?php

namespace craftnet\payouts;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $payoutId
 * @property int $developerId
 * @property float $amount
 * @property string|null $payoutItemId
 * @property string|null $transactionId
 * @property string|null $transactionStatus
 * @property string|null $timeProcessed
 * @property float|null $fee
 * @property-read Payout $payout
 */
class PayoutItem extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'craftnet_payout_items';
    }

    /**
     * Returns the entry’s author.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getPayout(): ActiveQueryInterface
    {
        return $this->hasOne(Payout::class, ['id' => 'payoutId']);
    }
}
