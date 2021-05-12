<?php

namespace craftnet\gql\interfaces\elements;

use craft\gql\types\DateTime;
use craftnet\gql\types\generators\PartnerType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\interfaces\elements\Asset;
use craft\gql\interfaces\elements\User;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use craft\helpers\Gql;
use GraphQL\Type\Definition\ObjectType;

/**
 * Class Partner
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Partner extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return PartnerType::class;
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
            'description' => 'This is the interface implemented by all partners.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        PartnerType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'PartnerInterface';
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
                'ownerId' => [
                    'name' => 'ownerId',
                    'type' => Type::id(),
                    'description' => 'User account ID of the partner listing’s owner.'
                ],
                'businessName' => [
                    'name' => 'businessName',
                    'type' => Type::string(),
                    'description' => 'Partner’s marketing-friendly business name.'
                ],
                'primaryContactName' => [
                    'name' => 'primaryContactName',
                    'type' => Type::string(),
                    'description' => 'Full name of partner’s primary human contact.'
                ],
                'primaryContactEmail' => [
                    'name' => 'primaryContactEmail',
                    'type' => Type::string(),
                    'description' => 'Email address to use contacting the partner.'
                ],
                'primaryContactPhone' => [
                    'name' => 'primaryContactPhone',
                    'type' => Type::string(),
                    'description' => 'Phone number to use contacting the partner.'
                ],
                'fullBio' => [
                    'name' => 'fullBio',
                    'type' => Type::string(),
                    'description' => 'Partner bio.'
                ],
                'shortBio' => [
                    'name' => 'shortBio',
                    'type' => Type::string(),
                    'description' => 'Brief partner bio.'
                ],
                'isCraftVerified' => [
                    'name' => 'isCraftVerified',
                    'type' => Type::boolean(),
                    'description' => 'Whether the partner is Craft Verified.'
                ],
                'isCommerceVerified' => [
                    'name' => 'isCommerceVerified',
                    'type' => Type::boolean(),
                    'description' => 'Whether the partner is Commerce Verified.'
                ],
                'isEnterpriseVerified' => [
                    'name' => 'isEnterpriseVerified',
                    'type' => Type::boolean(),
                    'description' => 'Whether the partner is Enterprise Verified.'
                ],
                'isRegisteredBusiness' => [
                    'name' => 'isRegisteredBusiness',
                    'type' => Type::boolean(),
                    'description' => 'Whether the partner has a registered business entity.',
                ],
                'hasFullTimeDev' => [
                    'name' => 'hasFullTimeDev',
                    'type' => Type::boolean(),
                    'description' => 'Whether the partner has at least one full-time developer.'
                ],
                'agencySize' => [
                    'name' => 'agencySize',
                    'type' => Type::string(),
                    'description' => 'Partner employee head count.'
                ],
                'verificationStartDate' => [
                    'name' => 'verificationStartDate',
                    'type' => DateTime::getType(),
                    'description' => 'Date at which partner was first or most recently considered verified.'
                ],
                'region' => [
                    'name' => 'region',
                    'type' => Type::string(),
                    'description' => 'Narrows query results based on geographic regions.'
                ],
                'capabilities' => [
                    'name' => 'capabilities',
                    'type' => Type::listOf(Type::string()),
                    'description' => 'List of partner capabilities.'
                ],
                'expertise' => [
                    'name' => 'expertise',
                    'type' => Type::string(),
                    'description' => 'Newline-separated labels that succinctly describe partner competency/experience areas.'
                ],
                'websiteSlug' => [
                    'name' => 'websiteSlug',
                    'type' => Type::string(),
                    'description' => 'Slug to be used for public partner listing.'
                ],
                'logoAssetId' => [
                    'name' => 'logoAssetId',
                    'type' => Type::id(),
                    'description' => 'Partner logo asset ID.'
                ],
                'website' => [
                    'name' => 'website',
                    'type' => Type::string(),
                    'description' => 'URL for partner’s website.'
                ],
                'logo' => [
                    'name' => 'logo',
                    'type' => Asset::getType(),
                    'description' => 'The partner’s photo.',
                    'complexity' => Gql::eagerLoadComplexity(),
                ],
                'owner' => [
                    'name' => 'owner',
                    'type' => User::getType(),
                    'description' => 'Person responsible for the partner listing.',
                    'complexity' => Gql::eagerLoadComplexity(),
                ],
                'locations' => [
                    'name' => 'locations',
                    'type' => Type::listOf(self::_getLocationGqlType()),
                    'description' => 'Partner’s physical locations.',
                ],
                'projects' => [
                    'name' => 'projects',
                    'type' => Type::listOf(self::_getProjectGqlType()),
                    'description' => 'Partner’s portfolio projects.',
                ],
            ]
        ), self::getName());
    }

    /**
     * Returns Partner Location type definition.
     * @return ObjectType
     */
    private static function _getLocationGqlType(): ObjectType
    {
        $typeName = 'Partner_Location';
        $locationGqlType = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new ObjectType([
                'name' => $typeName,
                'fields' => [
                    'id' => [
                        'name' => 'id',
                        'type' => Type::id(),
                        'description' => 'Partner location ID.'
                    ],
                    'title' => [
                        'name' => 'title',
                        'type' => Type::string(),
                        'description' => 'Partner location name.'
                    ],
                    'addressLine1' => [
                        'name' => 'addressLine1',
                        'type' => Type::string(),
                        'description' => 'First line of partner location street address.'
                    ],
                    'addressLine2' => [
                        'name' => 'addressLine2',
                        'type' => Type::string(),
                        'description' => 'Second line of partner location street address.'
                    ],
                    'city' => [
                        'name' => 'city',
                        'type' => Type::string(),
                        'description' => 'Partner location city.'
                    ],
                    'state' => [
                        'name' => 'state',
                        'type' => Type::string(),
                        'description' => 'Partner location state or province.'
                    ],
                    'zip' => [
                        'name' => 'zip',
                        'type' => Type::string(),
                        'description' => 'Partner location postal code.'
                    ],
                    'country' => [
                        'name' => 'country',
                        'type' => Type::string(),
                        'description' => 'Partner location country.'
                    ],
                    'phone' => [
                        'name' => 'phone',
                        'type' => Type::string(),
                        'description' => 'Partner location phone number.'
                    ],
                    'email' => [
                        'name' => 'email',
                        'type' => Type::string(),
                        'description' => 'Partner location email address.'
                    ],
                    'dateCreated' => [
                        'name' => 'dateCreated',
                        'type' => DateTime::getType(),
                        'description' => 'When the project was added to the partner profile.'
                    ],
                    'dateUpdated' => [
                        'name' => 'dateUpdated',
                        'type' => DateTime::getType(),
                        'description' => 'When the project was last updated in the partner profile.'
                    ],
                ],
            ]));

        return $locationGqlType;
    }

    /**
     * Returns Partner Project type definition.
     * @return ObjectType
     */
    private static function _getProjectGqlType(): ObjectType
    {
        $typeName = 'Partner_Project';
        $projectGqlType = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new ObjectType([
                'name' => $typeName,
                'fields' => [
                    'id' => [
                        'name' => 'id',
                        'type' => Type::id(),
                        'description' => 'Partner project ID.'
                    ],
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                        'description' => 'Project name.'
                    ],
                    'url' => [
                        'name' => 'url',
                        'type' => Type::string(),
                        'description' => 'Project URL.'
                    ],
                    'linkType' => [
                        'name' => 'linkType',
                        'type' => Type::string(),
                        'description' => 'Project link type; website or case study.'
                    ],
                    'role' => [
                        'name' => 'role',
                        'type' => Type::string(),
                        'description' => 'Partner’s role in the project.'
                    ],
                    'withCraftCommerce' => [
                        'name' => 'withCraftCommerce',
                        'type' => Type::boolean(),
                        'description' => 'Whether the project included Craft Commerce.'
                    ],
                    'dateCreated' => [
                        'name' => 'dateCreated',
                        'type' => DateTime::getType(),
                        'description' => 'When the project was added to the partner profile.'
                    ],
                    'dateUpdated' => [
                        'name' => 'dateUpdated',
                        'type' => DateTime::getType(),
                        'description' => 'When the project was last updated in the partner profile.'
                    ],
                    'sortOrder' => [
                        'name' => 'sortOrder',
                        'type' => Type::int(),
                        'description' => 'Numeric sort order.'
                    ],
                    'screenshots' => [
                        'name' => 'screenshots',
                        'type' => Type::listOf(Asset::getType()),
                        'description' => 'Project screenshots.'
                    ],
                ]
            ])
        );

        return $projectGqlType;
    }

    /**
     * @inheritdoc
     */
    protected static function getConditionalFields(): array
    {
        return [];
    }

}
