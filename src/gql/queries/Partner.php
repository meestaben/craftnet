<?php

namespace craftnet\gql\queries;

use craft\gql\base\Query;
use craftnet\gql\interfaces\elements\Partner as PartnerInterface;
use craftnet\gql\arguments\elements\Partner as PartnerArguments;
use craftnet\gql\resolvers\elements\Partner as PartnerResolver;
use GraphQL\Type\Definition\Type;

/**
 * Class Partner
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Partner extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries($checkToken = true): array
    {
        return [
            'partners' => [
                'type' => Type::listOf(PartnerInterface::getType()),
                'args' => PartnerArguments::getArguments(),
                'resolve' => PartnerResolver::class . '::resolve',
                'description' => 'This query is used to query for partners.'
            ],
            'partner' => [
                'type' => PartnerInterface::getType(),
                'args' => PartnerArguments::getArguments(),
                'resolve' => PartnerResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a partner.'
            ],
        ];
    }
}
