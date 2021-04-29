<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftnet\records;

use craft\db\ActiveRecord;
use craft\records\Asset;
use craft\records\User;
use craftnet\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Class Plugin record.
 *
 * @property int $id ID
 * @property int $developerId Developer ID
 * @property int $packageId Package ID
 * @property int|null $iconId Icon ID
 * @property string $packageName Package name
 * @property string $repository Repository
 * @property string $name Name
 * @property string $handle Handle
 * @property float|null $price Price
 * @property float|null $renewalPrice Renewal price
 * @property string $license License
 * @property string|null $shortDescription Short description
 * @property string|null $longDescription Long description
 * @property string|null $documentationUrl Documentation URL
 * @property string|null $changelogPath Changelog path
 * @property string|null $lastVersion Last version
 * @property bool $pendingApproval Pending approval?
 * @property int $activeInstalls
 * @property string|null $keywords Keywords
 */
class Plugin extends ActiveRecord
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName(): string
    {
        return Table::PLUGINS;
    }

    /**
     * Returns the plugin's developer.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'developerId']);
    }

    /**
     * Returns the plugin's icon asset.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getIcon(): ActiveQueryInterface
    {
        return $this->hasOne(Asset::class, ['id' => 'iconId']);
    }
}
