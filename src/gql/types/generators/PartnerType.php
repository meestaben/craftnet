<?php

namespace craftnet\gql\types\generators;

use craftnet\partners\Partner as PartnerElement;
use craftnet\gql\interfaces\elements\Partner as PartnerInterface;
use craftnet\gql\types\elements\Partner;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use craft\gql\base\ObjectType;

/**
 * Class PartnerType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PartnerType implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
    {
        // Partners have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType($context): ObjectType
    {
        $partnerType = new PartnerElement();
        $typeName = $partnerType->getGqlTypeName();
        $partnerFields = TypeManager::prepareFieldDefinitions(PartnerInterface::getFieldDefinitions(), $typeName);

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new Partner([
            'name' => $typeName,
            'fields' => function() use ($partnerFields) {
                return $partnerFields;
            },
        ]));
    }
}
