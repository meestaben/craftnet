<?php

namespace craftnet\payouts;

use craft\db\ActiveRecord;
use craftnet\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property string|null $payoutBatchId
 * @property string|null $status
 * @property string|null $timeCompleted
 * @property-read PayoutItem[] $items
 */
class Payout extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return Table::PAYOUTS;
    }

    /**
     * Returns the entry’s author.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getItems(): ActiveQueryInterface
    {
        return $this->hasMany(PayoutItem::class, ['payoutId' => 'id']);
    }
}
