<?php

namespace craftnet\behaviors;

use craft\elements\Asset;
use yii\base\Behavior;

/**
 * @property Asset $owner
 */
class AssetBehavior extends Behavior
{
    /**
     * @var int|null The ID of the project that this asset is associated with, if it happens to be a project screenshot.
     */
    public $projectId;
}
