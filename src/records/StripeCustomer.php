<?php

namespace craftnet\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * Class StripeCustomer
 *
 * @property int $id
 * @property int $userId
 * @property int $gatewayId
 * @property string $reference
 * @property string|null $response
 * @property ActiveQueryInterface $user
 */
class StripeCustomer extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%stripe_customers}}';
    }

    /**
     * Returns the Customer’s user.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
