<?php

namespace craftnet\oauthserver\server\Repositories;

use craftnet\oauthserver\models\RefreshToken;
use craftnet\oauthserver\Module as OauthServer;
use craftnet\oauthserver\server\Entities\RefreshTokenEntity;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    // Traits
    // =========================================================================

    use RefreshTokenTrait;
    use EntityTrait;

    // Public Methods
    // =========================================================================

    /**
     * Creates a new refresh token
     *
     * @return RefreshTokenEntityInterface
     */
    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
    }

    /**
     * Create a new refresh token_name.
     *
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        $accessTokenIdentifier = $refreshTokenEntity->getAccessToken()->getIdentifier();
        $accessToken = OauthServer::getInstance()->getAccessTokens()->getAccessTokenByIdentifier($accessTokenIdentifier);

        $refreshToken = new RefreshToken;
        $refreshToken->accessTokenId = $accessToken->id;
        $refreshToken->identifier = $refreshTokenEntity->getIdentifier();
        $refreshToken->expiryDate = $refreshTokenEntity->getExpiryDateTime();

        OauthServer::getInstance()->getRefreshTokens()->saveRefreshToken($refreshToken);
    }

    /**
     * Revoke the refresh token.
     *
     * @param string $tokenId
     * @return bool
     */
    public function revokeRefreshToken($tokenId)
    {
        $refreshToken = OauthServer::getInstance()->getRefreshTokens()->getRefreshTokenByIdentifier($tokenId);

        if (!$refreshToken) {
            return false;
        }

        return OauthServer::getInstance()->getRefreshTokens()->deleteRefreshTokenById($refreshToken->id);
    }

    /**
     * Check if the refresh token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        $refreshToken = OauthServer::getInstance()->getRefreshTokens()->getRefreshTokenByIdentifier($tokenId);

        if ($refreshToken) {
            return false;
        }

        return true;
    }
}
