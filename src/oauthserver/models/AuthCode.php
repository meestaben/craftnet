<?php

namespace craftnet\oauthserver\models;

use Craft;
use craft\base\Model;
use craftnet\oauthserver\Module;

/**
 * Class AuthCode
 */
class AuthCode extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $id;

    /**
     * @var
     */
    public $clientId;

    /**
     * @var
     */
    public $userId;

    /**
     * @var
     */
    public $identifier;

    /**
     * @var \DateTime
     */
    public $expiryDate;

    /**
     * @var
     */
    public $scopes;

    /**
     * @var
     */
    public $dateCreated;

    /**
     * @var
     */
    public $dateUpdated;

    /**
     * @var
     */
    public $uid;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'expiryDate';
        return $attributes;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return Module::getInstance()->getClients()->getClientById($this->clientId);
    }

    /**
     * @return \craft\elements\User|null
     */
    public function getUser()
    {
        return Craft::$app->getUsers()->getUserById($this->userId);
    }
}
