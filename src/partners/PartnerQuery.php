<?php

namespace craftnet\partners;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftnet\db\Table;
use yii\db\Connection;

/**
 * @method Partner[]|array all($db = null)
 * @method Partner|array|null one($db = null)
 * @method Partner|array|null nth(int $n, Connection $db = null)
 */
class PartnerQuery extends ElementQuery
{
    /**
     * @var string|string[]|null Id of the managing user
     */
    public $ownerId;

    /**
     * @var int|int[]|null
     */
    public $agencySize;

    /**
     * @var bool
     */
    public $isCraftVerified;

    /**
     * @var bool
     */
    public $isCommerceVerified;

    /**
     * @var bool
     */
    public $isEnterpriseVerified;

    /**
     * @var string
     */
    public $region;

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Sets the [[businessName]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function businessName($value)
    {
        $this->businessName = $value;
        return $this;
    }

    public function region($value)
    {
        $this->region = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftnet_partners');

        $this->query->select([
            Table::PARTNERS . '.ownerId',
            Table::PARTNERS . '.businessName',
            Table::PARTNERS . '.primaryContactName',
            Table::PARTNERS . '.primaryContactEmail',
            Table::PARTNERS . '.primaryContactPhone',
            Table::PARTNERS . '.fullBio',
            Table::PARTNERS . '.shortBio',
            Table::PARTNERS . '.agencySize',
            Table::PARTNERS . '.hasFullTimeDev',
            Table::PARTNERS . '.isCraftVerified',
            Table::PARTNERS . '.isCommerceVerified',
            Table::PARTNERS . '.isEnterpriseVerified',
            Table::PARTNERS . '.verificationStartDate',
            Table::PARTNERS . '.isRegisteredBusiness',
            Table::PARTNERS . '.region',
            Table::PARTNERS . '.expertise',
            Table::PARTNERS . '.websiteSlug',
            Table::PARTNERS . '.logoAssetId',
            Table::PARTNERS . '.website',
        ]);

        $andWhereColumns = [
            'ownerId',
            'agencySize',
            'isCraftVerified',
            'isCommerceVerified',
            'isEnterpriseVerified',
            'region',
        ];

        foreach ($andWhereColumns as $column) {
            if (isset($this->{$column})) {
                $this->subQuery->andWhere(
                    Db::parseParam(Table::PARTNERS . '.' . $column, $this->{$column})
                );
            }
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
//        if ($status === Plugin::STATUS_PENDING) {
//            return ['elements.enabled' => false, 'craftnet_plugins.pendingApproval' => true];
//        }

        return parent::statusCondition($status);
    }
}
