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
use GraphQL\Type\Definition\ObjectType;
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
                    'type' => Type::ID(),
                    'description' => ''
                ],
                'packageId' => [
                    'name' => 'packageId',
                    'type' => Type::ID(),
                    'description' => ''
                ],
                'iconId' => [
                    'name' => 'iconId',
                    'type' => Type::ID(),
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
                    'type' => Type::ID(),
                    'description' => ''
                ],
                'editions' => [
                    'name' => 'editions',
                    'type' => Type::listOf(self::_getEditionGqlType()),
                    'description' => ''
                ],
                'developer' => [
                    'name' => 'developer',
                    'type' => User::getType(),
                    'description' => ''
                ],
                'package' => [
                    'name' => 'package',
                    'type' => self::_getPackageGqlType(),
                    'description' => ''
                ],
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

    /**
     * Returns Plugin Edition type definition.
     * @return bool|mixed
     */
    private static function _getEditionGqlType()
    {
        $typeName = 'Plugin_Edition';
        $featureTypeName = $typeName . '_Feature';
        $pluginFeatureGqlType = GqlEntityRegistry::getEntity($featureTypeName)
            ?: GqlEntityRegistry::createEntity($featureTypeName, new ObjectType([
                'name' => $featureTypeName,
                'fields' => [
                    'id' => [
                        'name' => 'id',
                        'type' => Type::ID(),
                        'description' => ''
                    ],
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'description' => [
                        'name' => 'description',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                ]
            ])
        );

        $editionGqlType = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new ObjectType([
                'name' => $typeName,
                'fields' => [
                    'id' => [
                        'name' => 'id',
                        'type' => Type::ID(),
                        'description' => ''
                    ],
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'fullName' => [
                        'name' => 'fullName',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'handle' => [
                        'name' => 'handle',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'price' => [
                        'name' => 'price',
                        'type' => Type::float(),
                        'description' => ''
                    ],
                    'renewalPrice' => [
                        'name' => 'renewalPrice',
                        'type' => Type::float(),
                        'description' => ''
                    ],
                    'features' => [
                        'name' => 'features',
                        'type' => Type::listOf($pluginFeatureGqlType),
                        'description' => ''
                    ],
                ],
            ])
        );

        return $editionGqlType;
    }

    /**
     * Returns Plugin Package type definition.
     * @return bool|mixed
     */
    private static function _getPackageGqlType()
    {
        $typeName = 'Plugin_Package';
        $packageGqlType = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new ObjectType([
                'name' => $typeName,
                'fields' => [
                    'id' => [
                        'name' => 'id',
                        'type' => Type::ID(),
                        'description' => ''
                    ],
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'type' => [
                        'name' => 'type',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'repository' => [
                        'name' => 'repository',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'managed' => [
                        'name' => 'managed',
                        'type' => Type::boolean(),
                        'description' => ''
                    ],
                    'abandoned' => [
                        'name' => 'abandoned',
                        'type' => Type::boolean(),
                        'description' => ''
                    ],
                    'replacementPackage' => [
                        'name' => 'replacementPackage',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'latestVersion' => [
                        'name' => 'latestVersion',
                        'type' => Type::string(),
                        'description' => ''
                    ],
                    'webhookId' => [
                        'name' => 'webhookId',
                        'type' => Type::ID(),
                        'description' => ''
                    ],
//                    'webhookSecret' => [
//                        'name' => 'webhookSecret',
//                        'type' => Type::string(),
//                        'description' => ''
//                    ],
                    // vcs
                ],
            ])
        );

        return $packageGqlType;
    }
}
