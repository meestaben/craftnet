<?php

namespace craftnet\gql\resolvers\elements;

use craft\gql\base\ElementResolver;
use craftnet\plugins\Plugin as PluginElement;
use craftnet\helpers\Gql as GqlHelper;

/**
 * Class Plugin
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Plugin extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = PluginElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it’s preloaded, it’s preloaded.
        if (is_array($query)) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } elseif (property_exists($query, $key)) {
                $query->$key = $value;
            } else {
                // Catch custom field queries
                $query->$key($value);
            }
        }

        if (!GqlHelper::canQueryPartners()) {
            return [];
        }

        return $query;
    }

}