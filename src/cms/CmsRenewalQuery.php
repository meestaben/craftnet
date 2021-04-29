<?php

namespace craftnet\cms;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftnet\db\Table;
use yii\db\Connection;

/**
 * @method CmsRenewal[]|array all($db = null)
 * @method CmsRenewal|array|null one($db = null)
 * @method CmsRenewal|array|null nth(int $n, Connection $db = null)
 */
class CmsRenewalQuery extends ElementQuery
{
    /**
     * @var int|int[]|null The CMS edition IDs(s) that the resulting renewals must be associated with.
     */
    public $editionId;

    /**
     * Sets the [[editionId]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function editionId($value)
    {
        $this->editionId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftnet_cmsrenewals');

        $this->query->select([
            Table::CMSRENEWALS . '.editionId',
            Table::CMSRENEWALS . '.price',
        ]);

        if ($this->editionId) {
            $this->subQuery->andWhere(Db::parseParam(Table::CMSRENEWALS . '.editionId', $this->editionId));
        }

        return parent::beforePrepare();
    }
}
