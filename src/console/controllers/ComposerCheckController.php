<?php

namespace craftnet\console\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craftnet\db\Table;
use craftnet\Module;
use craftnet\plugins\Plugin;
use Symfony\Component\Process\Process;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Show information about accounts
 *
 * @property Module $module
 */
class ComposerCheckController extends Controller
{
    /**
     *
     */
    public function actionIndex(): int
    {
        $cacheKey = 'composer-check:processed-plugins';
        $cache = Craft::$app->getCache();
        $packageManager = $this->module->getPackageManager();
        $processedPlugins = $cache->get($cacheKey) ?: [];
        $failures = [];
        $plugins = Plugin::find()->all();

        $testPath = dirname(CRAFT_BASE_PATH) . '/test';
        $jsonPath = $testPath . '/composer.json';
        $outputPath = $testPath . '/output';

        foreach ($plugins as $plugin) {
            if (isset($processedPlugins[$plugin->handle])) {
                continue;
            }

            $latestVersion = $packageManager->getLatestVersion($plugin->packageName);
            if (!$latestVersion) {
                $latestVersion = $packageManager->getLatestVersion($plugin->packageName, 'alpha');
                if (!$latestVersion) {
                    continue;
                }
            }

            FileHelper::writeToFile($jsonPath, Json::encode([
                'require' => [
                    $plugin->packageName => $latestVersion,
                ],
                'provide' => [
                    'roave/security-advisories' => 'dev-master',
                ],
                'repositories' => [
                    ['type' => 'composer', 'url' => 'https://composer.craftcms.com/'],
                    ['packagist.org' => false],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $this->stdout("\nInstalling $plugin->packageName@$latestVersion ...\n");
            $output = "$plugin->packageName@$latestVersion\n\n";
            $process = Process::fromShellCommandline("cd $testPath; composer update -o --no-suggest;");
            $process->setTimeout(600);
            $process->run(function($type, $data) use (&$output) {
                $output .= $data;
                $this->stdout($data, Console::FG_YELLOW);
            });

            if ($process->isSuccessful()) {
                $this->stdout("done\n", Console::FG_GREEN);
            } else {
                $this->stdout("failed\n", Console::FG_RED);
            }

            $packageOutputPath = $outputPath . "/$plugin->packageName.txt";
            $this->stdout("Writing output to $packageOutputPath ... ");
            FileHelper::writeToFile($packageOutputPath, $output);
            $this->stdout("done\n");

            $processedPlugins[$plugin->handle] = true;
            $cache->set($cacheKey, $processedPlugins);
        }

        $this->stdout("\nFinished processing plugins\n\n", Console::FG_GREEN);

        if (!empty($failures)) {
            $this->stdout("Failed packages:\n", Console::FG_RED);
            foreach ($failures as $failure) {
                $this->stdout("- $failure\n", Console::FG_RED);
            }
        }

        return ExitCode::OK;
    }

    public function actionGatherErrors(): int
    {
        $testPath = dirname(CRAFT_BASE_PATH) . '/test/output';
        $files = FileHelper::findFiles($testPath, ['only' => ['*.txt']]);

        $cacheKey = 'composer-check:processed-issues';
        $cache = Craft::$app->getCache();
        $processedPackages = $cache->get($cacheKey) ?: [];

        foreach ($files as $file) {
            // Any deprecation errors?
            $contents = file_get_contents($file);
            preg_match_all('/Deprecation Notice: Class (.*?) located in (.*?) does not comply with psr-4 autoloading standard\. It will not autoload anymore in Composer v2\.0\./', $contents, $matches, PREG_SET_ORDER);
            $notices = [];
            $commands = [];

            foreach ($matches as $match) {
                [, $className, $classFile] = $match;
                preg_match('/^\.\/vendor\/(.*?\/.*?)\//', $classFile, $packageMatch);
                $packageName = $packageMatch[1];
                $classFile = substr($classFile, strlen($packageMatch[0]));

                if (isset($processedPackages[$packageName])) {
                    continue;
                }

                $notices[$packageName][] = "> Deprecation Notice: Class `$className` located in `$match[2]` does not comply with psr-4 autoloading standard\. It will not autoload anymore in Composer v2\.0\.";

                // Figure out where the autoload namespace-relative class name begins
                $classParts = explode('\\', $className);
                while (count($classParts) > 1) {
                    array_shift($classParts);
                    $testClassPath = implode('/', $classParts) . '.php';
                    if (($pos = stripos($classFile, $testClassPath)) !== false) {
                        $basePath = substr($classFile, 0, $pos);
                        $pathParts = explode('/', substr($classFile, $pos));
                        $totalParts = count($pathParts);

                        // Sanity check
                        if ($totalParts !== count($classParts)) {
                            $this->stderr('Expected ' . implode('/', $pathParts) . ' and ' . implode('\\', $classParts) . " to have the same number of segments\n", Console::FG_RED);
                        }

                        for ($i = $totalParts - 1; $i >= 0; $i--) {
                            // Is this the file name?
                            if ($isFile = (strpos($pathParts[$i], '.php') !== false)) {
                                $pathPart = pathinfo($pathParts[$i], PATHINFO_FILENAME);
                            } else {
                                $pathPart = $pathParts[$i];
                            }

                            $classPart = $classParts[$i];

                            if ($pathPart !== $classPart) {
                                $oldPath = $basePath . implode('/', array_slice($pathParts, 0, $i + 1));
                                $newPath = dirname($oldPath) . '/' . $classPart . ($isFile ? '.php' : '');
                                $commands[$packageName]["> git mv $oldPath $newPath"] = true;
                            }
                        }

                        break;
                    }
                }

                $processedPackages[$packageName] = true;
            }

            foreach ($notices as $packageName => $packageNotices) {
                $this->stdout("\n$packageName ", Console::FG_CYAN);
                $this->stdout('(' . count($packageNotices) . ' ' . (count($packageNotices) === 1 ? 'issue' : 'issues') . ")\n");

                $package = (new Query())
                    ->select(['id', 'repository'])
                    ->from(Table::PACKAGES)
                    ->where(['name' => $packageName])
                    ->one();

                if (!empty($package['repository'])) {
                    $repoUrl = $package['repository'];
                } else {
                    $this->stdout("Unknown repo URL for $packageName.\n", Console::FG_RED);
                    $this->stdout('Get it from ');
                    $this->stdout("https://packagist.org/packages/$packageName\n", Console::FG_BLUE);
                    $repoUrl = $this->prompt('Repo URL:');
                }

                $packageCommands = array_keys($commands[$packageName] ?? []);

                if (empty($packageCommands)) {
                    $this->stdout("Unable to determine the class/file mapping.\n", Console::FG_RED);
                    $packageCommands[] = "> git mv OLD_PATH NEW_PATH";
                }

                usort($packageCommands, function($a, $b) {
                    return strlen($b) - strlen($a);
                });

                $noticeMsg = count($packageNotices) === 1 ? 'notice is' : 'notices are';
                $thisMsg = count($packageNotices) === 1 ? 'this gets' : 'these get';
                $commandMsg = count($packageCommands) === 1 ? 'command' : 'commands';
                $changeMsg = count($packageCommands) === 1 ? 'change' : 'changes';
                $issueBody = "When installing this plugin with Composer 1, the following deprecation $noticeMsg output:\n\n" .
                    implode("\n\n", $packageNotices) . "\n\n" .
                    "Composer 2 was released on October 24, and is now the default version that will be installed, so it’s critical that $thisMsg resolved ASAP, or people will start getting `Class not found` errors.\n\n" .
                    "To fix, run the following $commandMsg:\n\n" .
                    "```bash\n" . implode("\n", $packageCommands) . "\n```\n\n" .
                    "Then commit your $changeMsg and [tag a new release](https://craftcms.com/docs/3.x/extend/plugin-store.html#plugin-releases).\n\n" .
                    "_(Note that you must use the `git mv` command, as Git tends to not notice case-sensitive file renames otherwise.)_";

                $this->stdout('Issue URL: ');
                $this->stdout("$repoUrl/issues/new?title=" . urlencode('Fix Composer 2 compatibility') . '&body=' . urlencode($issueBody) . "\n", Console::FG_BLUE);

                if (!$this->confirm('Continue?', ['default' => true])) {
                    break 2;
                }

                $processedPackages[$packageName] = true;
                $cache->set($cacheKey, $processedPackages);
            }
        }

        return ExitCode::OK;
    }
}
