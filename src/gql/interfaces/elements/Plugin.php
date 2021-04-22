<?php

namespace craftnet\gql\interfaces\elements;

use craft\gql\types\DateTime;
use craftnet\gql\types\generators\PluginType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\interfaces\elements\Asset;
use craft\gql\interfaces\elements\Category;
use craft\gql\interfaces\elements\User;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Plugin
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Plugin extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return PluginType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all plugins.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        PluginType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'PluginInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return TypeManager::prepareFieldDefinitions(array_merge(
            parent::getFieldDefinitions(),
            self::getConditionalFields(),
            [
                'enabled' => [
                    'name' => 'enabled',
                    'type' => Type::boolean(),
                    'description' => ''
                ],
                'published' => [
                    'name' => 'published',
                    'type' => Type::boolean(),
                    'description' => ''
                ],
                'developerId' => [
                    'name' => 'developerId',
                    'type' => Type::int(),
                    'description' => ''
                ],
                'packageId' => [
                    'name' => 'packageId',
                    'type' => Type::int(),
                    'description' => ''
                ],
                'iconId' => [
                    'name' => 'iconId',
                    'type' => Type::int(),
                    'description' => ''
                ],
                'packageName' => [
                    'name' => 'packageName',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'repository' => [
                    'name' => 'repository',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'name' => [
                    'name' => 'name',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'handle' => [
                    'name' => 'handle',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'license' => [
                    'name' => 'license',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'shortDescription' => [
                    'name' => 'shortDescription',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'longDescription' => [
                    'name' => 'longDescription',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'documentationUrl' => [
                    'name' => 'documentationUrl',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'changelogPath' => [
                    'name' => 'changelogPath',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'latestVersion' => [
                    'name' => 'latestVersion',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'latestVersionTime' => [
                    'name' => 'latestVersionTime',
                    'type' => DateTime::getType(),
                    'description' => ''
                ],
                'activeInstalls' => [
                    'name' => 'activeInstalls',
                    'type' => Type::int(),
                    'description' => ''
                ],
                'devComments' => [
                    'name' => 'devComments',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'pendingApproval' => [
                    'name' => 'pendingApproval',
                    'type' => Type::boolean(),
                    'description' => ''
                ],
                'keywords' => [
                    'name' => 'keywords',
                    'type' => Type::string(),
                    'description' => ''
                ],
                'dateApproved' => [
                    'name' => 'dateApproved',
                    'type' => DateTime::getType(),
                    'description' => ''
                ],
                'totalPurchases' => [
                    'name' => 'totalPurchases',
                    'type' => Type::int(),
                    'description' => ''
                ],
                'abandoned' => [
                    'name' => 'abandoned',
                    'type' => Type::boolean(),
                    'description' => ''
                ],
                'replacementId' => [
                    'name' => 'replacementId',
                    'type' => Type::int(),
                    'description' => ''
                ],
                // editions
                // allEditions
                'developer' => [
                    'name' => 'developer',
                    'type' => User::getType(),
                    'description' => ''
                ],
                // package
                'icon' => [
                    'name' => 'icon',
                    'type' => Asset::getType(),
                    'description' => ''
                ],
                'categories' => [
                    'name' => 'categories',
                    'type' => Type::listOf(Category::getType()),
                    'description' => ''
                ],
                'screenshots' => [
                    'name' => 'screenshots',
                    'type' => Type::listOf(Asset::getType()),
                    'description' => ''
                ],
                'hasMultipleEditions' => [
                    'name' => 'hasMultipleEditions',
                    'type' => Type::boolean(),
                    'description' => ''
                ],
            ]
        ), self::getName());
    }

    /**
     * @inheritdoc
     */
    protected static function getConditionalFields(): array
    {
        return [];
    }

    private static function _getEditionsGqlType()
    {

    }

    private static function _getPackageGqlType()
    {

    }

}
