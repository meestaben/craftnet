<?php

namespace craftnet\gql\interfaces\elements;

use craft\gql\types\DateTime;
use craftnet\gql\types\generators\PartnerType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

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
        return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::int(),
                'description' => ''
            ],
            'businessName' => [
                'name' => 'businessName',
                'type' => Type::string(),
                'description' => ''
            ],
            'primaryContactName' => [
                'name' => 'primaryContactName',
                'type' => Type::string(),
                'description' => ''
            ],
            'primaryContactEmail' => [
                'name' => 'primaryContactEmail',
                'type' => Type::string(),
                'description' => ''
            ],
            'primaryContactPhone' => [
                'name' => 'primaryContactPhone',
                'type' => Type::string(),
                'description' => ''
            ],
            'fullBio' => [
                'name' => 'fullBio',
                'type' => Type::string(),
                'description' => ''
            ],
            'isCraftVerified' => [
                'name' => 'isCraftVerified',
                'type' => Type::boolean(),
                'description' => ''
            ],
            'isCommerceVerified' => [
                'name' => 'isCommerceVerified',
                'type' => Type::boolean(),
                'description' => ''
            ],
            'isEnterpriseVerified' => [
                'name' => 'isEnterpriseVerified',
                'type' => Type::boolean(),
                'description' => ''
            ],
            'isRegisteredBusiness' => [
                'name' => 'isRegisteredBusiness',
                'type' => Type::boolean(),
                'description' => ''
            ],
            'hasFullTimeDev' => [
                'name' => 'hasFullTimeDev',
                'type' => Type::boolean(),
                'description' => ''
            ],
            'agencySize' => [
                'name' => 'agencySize',
                'type' => Type::string(),
                'description' => ''
            ],
            'verificationStartDate' => [
                'name' => 'verificationStartDate',
                'type' => DateTime::getType(),
                'description' => ''
            ],
            'region' => [
                'name' => 'region',
                'type' => Type::string(),
                'description' => 'Narrows query results based on geographic regions.'
            ],
            'expertise' => [
                'name' => 'expertise',
                'type' => Type::string(),
                'description' => ''
            ],
            'websiteSlug' => [
                'name' => 'websiteSlug',
                'type' => Type::string(),
                'description' => ''
            ],
            'logoAssetId' => [
                'name' => 'logoAssetId',
                'type' => Type::int(),
                'description' => ''
            ],
            'website' => [
                'name' => 'website',
                'type' => Type::string(),
                'description' => ''
            ],
            // locations (mysterious matrix-y field)
            // projects (mysterious matrix-y field)
        ]), self::getName());
    }
}
