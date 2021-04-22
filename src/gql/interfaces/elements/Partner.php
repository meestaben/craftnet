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
                    'type' => Type::int(),
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
                    'description' => 'Whether the partner has a registered business entity.'
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
                    'description' => 'Date at which partner verification process started.'
                ],
                'region' => [
                    'name' => 'region',
                    'type' => Type::string(),
                    'description' => 'Narrows query results based on geographic regions.'
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
                    'type' => Type::int(),
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
                // locations (mysterious matrix-y field)
                // projects (mysterious matrix-y field)
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

}
