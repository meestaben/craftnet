<?php

namespace craftnet\behaviors;

use craft\commerce\models\Discount;
use craftnet\cms\CmsEdition;
use yii\base\Behavior;

/**
 * @property Discount $owner
 */
class DiscountBehavior extends Behavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Discount::EVENT_AFTER_VALIDATE => [$this, 'afterValidate'],
        ];
    }

    /**
     * Handles post-validation stuff.
     */
    public function afterValidate()
    {
        if (empty($this->owner->getPurchasableIds())) {
            $attribute = 'purchasables-' . str_replace('\\', '-', CmsEdition::class);
            $this->owner->addError($attribute, 'A Craft or plugin purchasable selection is required.');
        }
    }
}
