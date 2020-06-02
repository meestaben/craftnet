<?php

namespace craftnet\controllers\id;

use Craft;
use craft\elements\Category;
use craft\elements\User;
use craftnet\developers\UserBehavior;
use yii\web\Response;

/**
 * Class PluginsController
 */
class PluginsController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * Get plugins.
     *
     * @return Response
     */
    public function actionGetPlugins(): Response
    {
        $this->requireLogin();

        /** @var User|UserBehavior $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();
        $data = [];

        foreach ($currentUser->getPlugins() as $plugin) {
            $data[] = $this->pluginTransformer($plugin);
        }

        return $this->asJson($data);
    }

    /**
     * Get categories.
     *
     * @return Response
     */
    public function actionGetCategories(): Response
    {
        $this->requireLogin();

        $data = [];
        $categories = Category::find()
            ->group('pluginCategories')
            ->all();

        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->id,
                'title' => $category->title,
            ];
        }

        return $this->asJson($data);
    }
}
