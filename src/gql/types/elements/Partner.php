<?php

namespace craftnet\gql\types\elements;

use craftnet\partners\Partner as PartnerElement;
use craftnet\gql\interfaces\elements\Partner as PartnerInterface;
use craft\gql\types\elements\Element as ElementType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Partner
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Partner extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            PartnerInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        /** @var PartnerElement $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }

}