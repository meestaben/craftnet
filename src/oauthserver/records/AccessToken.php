<?php

namespace craftnet\oauthserver\records;

use craft\db\ActiveRecord;

/**
 * Class AccessToken
 *
 * @property int $id
 * @property int $clientId
 * @property int|null $userId
 * @property string $identifier
 * @property string|null $expiryDate
 * @property string|null $userIdentifier
 * @property string|null $scopes
 * @property bool $isRevoked
 */
class AccessToken extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the name of the database table the model is associated with.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%oauthserver_access_tokens}}';
    }
}
