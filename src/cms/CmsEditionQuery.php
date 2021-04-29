<?php

namespace craftnet\cms;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftnet\db\Table;
use yii\db\Connection;

/**
 * @method CmsEdition[]|array all($db = null)
 * @method CmsEdition|array|null one($db = null)
 * @method CmsEdition|array|null nth(int $n, Connection $db = null)
 */
class CmsEditionQuery extends ElementQuery
{
    /**
     * @var string|string[]|null The handle(s) that the resulting editions must have.
     */
    public $handle;

    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'price';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * Sets the [[handle]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function handle($value)
    {
        $this->handle = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftnet_cmseditions');

        $this->query->select([
            Table::CMSEDITIONS . '.name',
            Table::CMSEDITIONS . '.handle',
            Table::CMSEDITIONS . '.price',
            Table::CMSEDITIONS . '.renewalPrice',
        ]);

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam(Table::CMSEDITIONS . '.handle', $this->handle));
        }

        return parent::beforePrepare();
    }
}
