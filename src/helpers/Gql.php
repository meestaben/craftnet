<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftnet\helpers;

use craft\helpers\Gql as GqlHelper;

/**
 * Class Gql
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Gql extends GqlHelper
{
    /**
     * Returns true if active schema can query partners.
     *
     * @return bool
     */
    public static function canQueryPartners(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['partners']);
    }

    /**
     * Returns true if active schema can query plugins.
     *
     * @return bool
     */
    public static function canQueryPlugins(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['plugins']);
    }
}
