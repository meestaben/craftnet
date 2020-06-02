<?php

namespace craftnet\oauthserver\records;

use craft\db\ActiveRecord;

/**
 * Class Client
 *
 * @property int $id
 * @property string $name
 * @property string $identifier
 * @property string|null $secret
 * @property string|null $redirectUri
 * @property bool $redirectUriLocked
 */
class Client extends ActiveRecord
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
        return '{{%oauthserver_clients}}';
    }
}
