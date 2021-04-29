<?php

namespace craftnet\partners;


use craft\db\Query;
use craft\helpers\ArrayHelper;
use craftnet\db\Table;

/**
 * Class PartnerCapabilites
 *
 * @package craftnet\partners
 */
class PartnerCapabilitiesQuery extends Query
{
    private $_partnerId;
    private $_asIndexedTitles = false;

    /**
     * Call to return results as indexed titles.
     * ```
     * [
     *     1 => 'Commerce',
     *     2 => 'Full Service',
     *     ...
     * ]
     * ```
     *
     * @return static
     */
    public function asIndexedTitles()
    {
        $this->_asIndexedTitles = true;

        return $this;
    }

    /**
     * @param int|Partner $partner
     * @return static
     */
    public function partner($partner): Query
    {
        $this->_partnerId = is_numeric($partner) ? $partner : $partner->id;

        return $this;
    }

    /**
     * @param \yii\db\QueryBuilder $builder
     * @return static
     */
    public function prepare($builder)
    {
        $this
            ->select(['pc.id id', 'pc.title title'])
            ->from(Table::PARTNERCAPABILITIES . ' pc');

        if (isset($this->_partnerId)) {
            $this
                ->innerJoin(Table::PARTNERS_PARTNERCAPABILITIES . ' p_pc', '[[p_pc.partnercapabilitiesId]] = [[pc.id]]')
                ->where(['p_pc.partnerId' => $this->_partnerId])
                ->andWhere('[[pc.id]] = [[p_pc.partnercapabilitiesId]]');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function populate($rows)
    {
        if ($this->_asIndexedTitles) {
            return ArrayHelper::map($rows, 'id', 'title');
        }

        return parent::populate($rows);
    }
}
