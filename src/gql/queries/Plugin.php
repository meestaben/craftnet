<?php

namespace craftnet\gql\queries;

use craft\gql\base\Query;
use craftnet\gql\interfaces\elements\Plugin as PluginInterface;
use craftnet\gql\arguments\elements\Plugin as PluginArguments;
use craftnet\gql\resolvers\elements\Plugin as PluginResolver;
use GraphQL\Type\Definition\Type;

/**
 * Class Plugin
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Plugin extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries($checkToken = true): array
    {
        return [
            'plugins' => [
                'type' => Type::listOf(PluginInterface::getType()),
                'args' => PluginArguments::getArguments(),
                'resolve' => PluginResolver::class . '::resolve',
                'description' => 'This query is used to query for plugins.'
            ],
            'plugin' => [
                'type' => PluginInterface::getType(),
                'args' => PluginArguments::getArguments(),
                'resolve' => PluginResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a plugin.'
            ],
        ];
    }
}
