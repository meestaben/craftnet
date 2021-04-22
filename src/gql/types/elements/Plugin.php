<?php

namespace craftnet\gql\types\elements;

use craftnet\plugins\Plugin as PluginElement;
use craftnet\gql\interfaces\elements\Plugin as PluginInterface;
use craft\gql\types\elements\Element as ElementType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Plugin
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Plugin extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            PluginInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        /** @var PluginElement $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}