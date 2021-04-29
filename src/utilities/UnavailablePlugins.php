<?php

namespace craftnet\utilities;

use Craft;
use craft\base\Utility;
use craft\db\Query;
use craftnet\db\Table;

class UnavailablePlugins extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Unavailable Plugins');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'unavailable-plugins';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/plugin.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $craft2Plugins = require(Craft::getAlias('@config/craft2-plugins.php'));

        $ignore = [];
        foreach ($craft2Plugins as $handle => $plugin) {
            if (isset($plugin['handle'])) {
                $ignore[] = $handle;
            }
        }

        $plugins = (new Query())
            ->select(['plugin', 'hits'])
            ->from([Table::CRAFT2PLUGINHITS])
            ->where(['available' => false])
            ->andWhere(['not', ['plugin' => $ignore]])
            ->orderBy(['hits' => SORT_DESC])
            ->pairs();

        return Craft::$app->getView()->renderTemplate('craftnet/unavailable-plugins/_content', [
            'plugins' => $plugins,
            'craft2Plugins' => $craft2Plugins,
        ]);
    }
}
