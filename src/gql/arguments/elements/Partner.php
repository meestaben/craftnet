<?php

namespace craftnet\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

/**
 * Class Partner
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Partner extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'region' => [
                'name' => 'region',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows query results based on geographic regions.'
            ],
            'isCraftVerified' => [
                'name' => 'isCraftVerified',
                'type' => Type::boolean(),
                'description' => 'Narrows query results based on Craft verification.'
            ],
            'isCommerceVerified' => [
                'name' => 'isCommerceVerified',
                'type' => Type::boolean(),
                'description' => 'Narrows query results based on Commerce verification.'
            ],
            'isEnterpriseVerified' => [
                'name' => 'isEnterpriseVerified',
                'type' => Type::boolean(),
                'description' => 'Narrows query results based on Enterprise verification.'
            ],
            'isRegisteredBusiness' => [
                'name' => 'isRegisteredBusiness',
                'type' => Type::boolean(),
                'description' => 'Narrows query results based on whether the partner is a registered business.'
            ],
            'hasFullTimeDev' => [
                'name' => 'hasFullTimeDev',
                'type' => Type::boolean(),
                'description' => 'Narrows query results based on whether the partner employs full-time developers.'
            ],
            'agencySize' => [
                'name' => 'agencySize',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows query results by agency size.'
            ],
            // locations (mysterious matrix-y field)
            // projects (mysterious matrix-y field)
        ]);
    }
}