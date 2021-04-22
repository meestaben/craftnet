<?php

namespace craftnet\gql\types\generators;

use craftnet\plugins\Plugin as PluginElement;
use craftnet\gql\interfaces\elements\Plugin as PluginInterface;
use craftnet\gql\types\elements\Plugin;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;
use craft\gql\base\ObjectType;

/**
 * Class PluginType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PluginType implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
    {
        // Plugins have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    public static function generateType($context): ObjectType
    {
        $pluginType = new PluginElement();
        $typeName = $pluginType->getGqlTypeName();
        $pluginFields = TypeManager::prepareFieldDefinitions(PluginInterface::getFieldDefinitions(), $typeName);

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new Plugin([
            'name' => $typeName,
            'fields' => function() use ($pluginFields) {
                return $pluginFields;
            },
        ]));
    }
}
