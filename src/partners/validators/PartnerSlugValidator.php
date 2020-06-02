<?php

namespace craftnet\partners\validators;


use Craft;
use craft\db\Query;
use craft\validators\SlugValidator;
use craftnet\partners\Partner;

class PartnerSlugValidator extends SlugValidator
{
    /**
     * @param Partner $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated.
     */
    public function validateAttribute($model, $attribute)
    {
        parent::validateAttribute($model, $attribute);

        // Unique
        if (!$model->hasErrors()) {
            $query = (new Query())
                ->select('COUNT(*)')
                ->from('craftnet_partners')
                ->where(['websiteSlug' => $model->$attribute])
                ->andWhere(['<>', 'id', $model->id]);

            $exists = (bool)$query->scalar();

            if ($exists) {
                $this->addError($model, $attribute, Craft::t('yii', '{attribute} is already taken.'));
            }
        }
    }
}
