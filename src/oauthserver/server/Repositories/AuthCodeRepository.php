<?php

namespace craftnet\oauthserver\server\Repositories;


use craftnet\oauthserver\models\AuthCode;
use craftnet\oauthserver\Module as OauthServer;
use craftnet\oauthserver\server\Entities\AuthCodeEntity;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    // Traits
    // =========================================================================

    use AuthCodeTrait;

    // Public Methods
    // =========================================================================

    /**
     * Creates a new AuthCode
     *
     * @return AuthCodeEntityInterface
     */
    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }

    /**
     * Persists a new auth code to permanent storage.
     *
     * @param AuthCodeEntityInterface $authCodeEntity
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $clientIdentifier = $authCodeEntity->getClient()->getIdentifier();
        $client = OauthServer::getInstance()->getClients()->getClientByIdentifier($clientIdentifier);

        $authCode = new AuthCode;
        $authCode->clientId = $client->id;
        $authCode->userId = $authCodeEntity->getUserIdentifier();
        $authCode->identifier = $authCodeEntity->getIdentifier();
        $authCode->expiryDate = $authCodeEntity->getExpiryDateTime();
        $authCode->scopes = $authCodeEntity->getScopes();

        OauthServer::getInstance()->getAuthCodes()->saveAuthCode($authCode);
    }

    /**
     * Revoke an auth code.
     *
     * @param string $codeId
     * @return bool
     */
    public function revokeAuthCode($codeId)
    {
        $authCode = OauthServer::getInstance()->getAuthCodes()->getAuthCodeByIdentifier($codeId);

        if (!$authCode) {
            return false;
        }

        return OauthServer::getInstance()->getAuthCodes()->deleteAuthCodeById($authCode->id);
    }

    /**
     * Check if the auth code has been revoked.
     *
     * @param string $codeId
     *
     * @return bool Return true if this code has been revoked
     */
    public function isAuthCodeRevoked($codeId)
    {
        $authCode = OauthServer::getInstance()->getAuthCodes()->getAuthCodeByIdentifier($codeId);

        if ($authCode) {
            return false;
        }

        return true;
    }
}
