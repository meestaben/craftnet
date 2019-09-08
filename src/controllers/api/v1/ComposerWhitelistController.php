<?php

namespace craftnet\controllers\api\v1;

use Composer\Semver\Comparator;
use craft\db\Query;
use craftnet\controllers\api\BaseApiController;
use craftnet\Module;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class ComposerWhitelistController
 */
class ComposerWhitelistController extends BaseApiController
{
    /**
     * @var array The dependencies to ignore when looking for additional whitelist dependencies
     */
    private $_ignoreDeps;

    /**
     * Handles /v1/optimize-composer-requirements requests.
     *
     * @return Response
     * @throws BadRequestHttpException if we don't have a record of any of the requested requirements
     */
    public function actionIndex(): Response
    {
        $payload = $this->getPayload('composer-whitelist-request');

        $whitelist = [
            'craftcms/vue-asset' => true,
            'danielstjules/stringy' => true,
        ];

        $this->_ignoreDeps = [
            '__root__' => true,
            'composer-plugin-api' => true,
            'php' => true,
            'php-64bit' => true,
            'php-ipv6' => true,
            'php-zts' => true,
            'php-debug' => true,
            'hhvm' => true,
            'craftcms/cms' => true,
        ];

        // Ignore composer/ca-bundle if they're running < Craft 3.0.22
        if (Comparator::lessThan($this->cmsVersion, '3.0.22')) {
            $this->_ignoreDeps['composer/ca-bundle'] = true;
        }

        // Start by setting specific versions on the to-be-installed requirements
        $install = (array)$payload->install;
        $packageManager = Module::getInstance()->getPackageManager();
        $releaseIds = [];

        foreach ($install as $name => $constraint) {
            $whitelist[$name] = true;

            if ($constraint === false) {
                continue;
            }

            // Strip off aliasing
            if (preg_match('/^([^,\s]++)( ++as ++[^,\s]++)$/', $constraint, $match)) {
                $constraint = $match[1];
            }

            // Assume that the latest version that meets this constraint is what they should get
            // (if it's not, they shouldn't have been given the impression they could update to it in the first place)
            if (($release = $packageManager->getLatestRelease($name, 'dev', $constraint)) === null) {
                throw new BadRequestHttpException("Unknown package/constraint: {$name}@{$constraint}");
            }

            $this->_ignoreDeps[$name] = true;
            $releaseIds[] = $release->id;
        }

        $deps = $this->_createDepQuery()
            ->andWhere(['versionId' => $releaseIds])
            ->column();

        $this->_addDepsToWhitelist($whitelist, $deps, (array)$payload->installed);

        return $this->asJson(array_keys($whitelist));
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
                ['not in', 'pd.name', array_keys($this->_ignoreDeps)],
                ['not like', 'pd.name', 'lib-%', false],
                ['not like', 'pd.name', 'ext-%', false],
                ['not like', 'pd.name', 'bower-asset/%', false],
                ['not like', 'pd.name', 'npm-asset/%', false],
            ]);
    }

    /**
     * Recursively adds dependencies to the whitelist.
     *
     * @param array $whitelist
     * @param string[] $deps
     * @param string[] $installed
     */
    private function _addDepsToWhitelist(array &$whitelist, array $deps, array $installed)
    {
        if (empty($deps)) {
            return;
        }

        foreach ($deps as $name) {
            if (isset($installed[$name])) {
                $whitelist[$name] = true;
            }

            // Ignore this dep in future dependency queries
            $this->_ignoreDeps[$name] = true;
        }

        // Add their deps to the mix as well
        $deps = $this->_createDepQuery()
            ->innerJoin('craftnet_packages p', '[[p.id]] = [[pd.packageId]]')
            ->andWhere(['p.name' => $deps])
            ->column();

        $this->_addDepsToWhitelist($whitelist, $deps, $installed);
    }
}
