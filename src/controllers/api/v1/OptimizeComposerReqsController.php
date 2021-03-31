<?php

namespace craftnet\controllers\api\v1;

use Composer\Semver\Comparator;
use craft\db\Query;
use craftnet\controllers\api\BaseApiController;
use craftnet\Module;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class UpdatesController
 */
class OptimizeComposerReqsController extends BaseApiController
{
    /**
     * @var array The packages to ignore from the list of installed packages.
     */
    private $_ignore = [];

    /**
     * Handles /v1/optimize-composer-requirements requests.
     *
     * @return Response
     * @throws BadRequestHttpException if we don't have a record of any of the requested requirements
     */
    public function actionIndex(): Response
    {
        $payload = $this->getPayload('optimize-composer-reqs-request');

        $optimized = [];

        // Ignore composer/ca-bundle if they're running < Craft 3.0.22
        if (Comparator::lessThan($this->cmsVersion, '3.0.22')) {
            $ignore['composer/ca-bundle'] = true;
        }

        // Start by setting specific versions on the to-be-installed requirements
        $install = (array)$payload->install;
        $packageManager = Module::getInstance()->getPackageManager();
        $releaseIds = [];

        foreach ($install as $name => $constraint) {
            // Strip off aliasing
            if (preg_match('/^([^,\s]++)( ++as ++[^,\s]++)$/', $constraint, $match)) {
                $constraint = $match[1];
                $alias = $match[2];
            } else {
                $alias = '';
            }

            // Assume that the latest version that meets this constraint is what they should get
            // (if it's not, they shouldn't have been given the impression they could update to it in the first place)
            if (($release = $packageManager->getLatestRelease($name, 'dev', $constraint)) === null) {
                throw new BadRequestHttpException("Unknown package/constraint: {$name}@{$constraint}");
            }

            $optimized[$name] = $release->version . $alias;
            $this->_ignore[$name] = true;
            $releaseIds[] = $release->id;
        }

        if (isset($payload->installed)) {
            // Don't include any of the to-be-installed releases' dependencies in the optimized requirements
            $deps = $this->_createDepQuery()
                ->andWhere(['versionId' => $releaseIds])
                ->column();
            $this->_ignoreInstalledDeps($deps);

            foreach ($payload->installed as $name => $version) {
                if (!isset($this->_ignore[$name]) && !isset($optimized[$name])) {
                    $optimized[$name] = $version;
                }
            }
        }

        return $this->asJson($optimized);
    }

    /**
     * Creates a new package dependency query.
     *
     * @return Query
     */
    private function _createDepQuery(): Query
    {
        return (new Query())
            //->select(['pd.name', 'pd.constraints'])
            ->select(['pd.name'])
            ->distinct()
            ->from(['craftnet_packagedeps pd'])
            ->where([
                'and',
                [
                    'not in', 'name', array_merge([
                    '__root__',
                    'composer-plugin-api',
                    'php',
                    'php-64bit',
                    'php-ipv6',
                    'php-zts',
                    'php-debug',
                    'hhvm',
                    'craftcms/cms',
                ], array_keys($this->_ignore)),
                ],
                ['not like', 'name', 'lib-%', false],
                ['not like', 'name', 'ext-%', false],
                ['not like', 'name', 'bower-asset/%', false],
                ['not like', 'name', 'npm-asset/%', false],
            ]);
    }

    /**
     * Adds a list of dependencies, and their recursive dependencies, to the ignore list
     *
     * @param string[] $deps
     */
    private function _ignoreInstalledDeps(array $deps)
    {
        if (empty($deps)) {
            return;
        }

        foreach ($deps as $name) {
            $this->_ignore[$name] = true;
        }

        // Get their package IDs
        $packageIds = (new Query())
            ->select(['id'])
            ->from(['craftnet_packages'])
            ->where(['name' => $deps])
            ->column();

        $deps = $this->_createDepQuery()
            ->andWhere(['packageId' => $packageIds])
            ->column();

        $this->_ignoreInstalledDeps($deps);
    }
}
