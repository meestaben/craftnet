<?php

namespace craftnet\gql\resolvers\elements;

use craft\gql\base\ElementResolver;
use craftnet\partners\Partner as PartnerElement;
use craftnet\helpers\Gql as GqlHelper;

/**
 * Class Partner
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Partner extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = PartnerElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it’s an array, it’s been preloaded.
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