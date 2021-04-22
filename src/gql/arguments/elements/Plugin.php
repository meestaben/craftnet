<?php

namespace craftnet\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

/**
 * Class Plugin
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Plugin extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'handle' => [
                'name' => 'handle',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows query results by plugin handle.'
            ],
            'developerId' => [
                'name' => 'developerId',
                'type' => Type::int(),
                'description' => 'Narrows query results by developer ID.'
            ],
            'license' => [
                'name' => 'license',
                'type' => Type::string(),
                'description' => 'Narrows query results based on license.'
            ],
        ]);
    }
}