<?php

namespace craftnet\controllers\api\v1;

use craftnet\composer\PackageRelease;
use craftnet\controllers\api\BaseApiController;
use craftnet\Module;
use yii\base\InvalidArgumentException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class PackageController
 */
class PackageController extends BaseApiController
{
    public $defaultAction = 'get';

    /**
     * Retrieves available system updates.
     *
     * @param string $packageName
     * @param string $minStability
     * @param string|null $constraint
     * @param string|null $include
     * @return Response
     * @throws \Throwable
     */
    public function actionGet(string $packageName, string $minStability = 'stable', string $constraint = null, string $include = null): Response
    {
        $packageManager = Module::getInstance()->getPackageManager();

        try {
            $package = $packageManager->getPackage($packageName);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        $r = $package->toArray([
            'name',
            'type',
            'repository',
            'managed',
            'abandoned',
            'replacementPackage',
        ]);

        if ($include !== null) {
            $include = array_flip(explode(',', $include));

            if (isset($include['releases'])) {
                $releases = [];
                foreach ($packageManager->getAllReleases($packageName, $minStability, $constraint) as $release) {
                    $releases[] = $this->_release($release);
                }
                $releases = array_reverse($releases);
            }
        }

        if (isset($releases)) {
            $r['latestRelease'] = $releases[0] ?? null;
            $r['releases'] = $releases;
        } else {
            $r['latestRelease'] = $this->_release($packageManager->getLatestRelease($packageName, $minStability, $constraint));
        }

        return $this->asJson($r);
    }

    private function _release(PackageRelease $release = null)
    {
        if (!$release) {
            return null;
        }

        return $release->toArray([
            'sha',
            'version',
            'time',
        ]);
    }
}
