<?php

namespace craftnet\controllers\api\v1\utils;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craftnet\controllers\api\BaseApiController;
use yii\web\Response;

/**
 * Class AccountController
 */
class Releases2ChangelogController extends BaseApiController
{
    /**
     * @inheritdoc
     */
    public $checkCraftHeaders = false;

    /**
     * Converts a releases.json file contents into a Markdown changelog
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $changelog = "# Changelog\n";

        $releases = (array)Json::decode(Craft::$app->getRequest()->getRawBody());

        // Filter out any releases w/out a version
        $releases = array_filter($releases, function($release) {
            return isset($release['version']);
        });

        // Sort latest => oldest
        $vp = new VersionParser();
        usort($releases, function($a, $b) use ($vp) {
            $a = $vp->normalize($a['version']);
            $b = $vp->normalize($b['version']);

            if (Comparator::equalTo($a, $b)) {
                return 0;
            }
            return Comparator::greaterThan($a, $b) ? -1 : 1;
        });

        foreach ($releases as $release) {
            if (empty($release['version'])) {
                continue;
            }

            $date = isset($release['date']) ? DateTimeHelper::toDateTime($release['date'], false, false) : false;
            $critical = !empty($release['critical']);

            $changelog .= "\n## {$release['version']}";
            if ($date) {
                $changelog .= ' - ' . $date->format('Y-m-d');
            }
            if ($critical) {
                $changelog .= ' [CRITICAL]';
            }
            $changelog .= "\n";

            if (isset($release['notes'])) {
                $notesByHeading = [];
                $otherNotes = '';
                $heading = null;

                foreach ($release['notes'] as $line) {
                    // Is this a heading?
                    if (preg_match('/^#\s+(.+)/', $line, $match)) {
                        $heading = $match[1];
                    } else {
                        if (preg_match('/^\[(\w+)\]\s+(.+)/', $line, $match)) {
                            $heading = ucfirst($match[1]);
                            if ($heading === 'Improved') {
                                $heading = 'Changed';
                            }
                            $line = $match[2];
                        }

                        if ($heading) {
                            if (!isset($notesByHeading[$heading])) {
                                $notesByHeading[$heading] = '';
                            }
                            $notesByHeading[$heading] .= "- {$line}\n";
                        } else {
                            $otherNotes .= "- {$line}\n";
                        }
                    }
                }

                // Uncategorized notes first
                if ($otherNotes) {
                    $changelog .= "\n{$otherNotes}";
                }

                foreach ($notesByHeading as $heading => $headingNotes) {
                    $changelog .= "\n### {$heading}\n{$headingNotes}";
                }
            }
        }

        Craft::$app->getResponse()->setDownloadHeaders('CHANGELOG.md', 'text/markdown', true);
        return $this->asRaw($changelog);
    }
}
