<?php

namespace craftnet\oauthserver\server\Repositories;

use craftnet\oauthserver\Module;
use craftnet\oauthserver\server\Entities\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Return information about a scope.
     *
     * @param string $identifier The scope identifier
     *
     * @return ScopeEntityInterface
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        $scopes = Module::getInstance()->getOauth()->getScopes();

        foreach ($scopes as $scope => $description) {
            if ($scope == $identifier) {
                $scopeEntity = new ScopeEntity();
                $scopeEntity->setIdentifier($identifier);

                return $scopeEntity;
            }
        }
    }

    /**
     * Given a client, grant type and optional user identifier validate the set of scopes requested are valid and optionally
     * append additional scopes or remove requested scopes.
     *
     * @param ScopeEntityInterface[] $scopes
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @param null|string $userIdentifier
     *
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    )
    {
        return $scopes;
    }
}
