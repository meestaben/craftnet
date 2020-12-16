<?php

namespace craftnet\console\controllers;

use Craft;
use craft\elements\User;
use craftnet\composer\Package;
use craftnet\Module;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Inflector;

/**
 * Manages Composer packages.
 *
 * @property Module $module
 */
class PackagesController extends Controller
{
    /**
     * @var bool Whether to update package releases even if their SHA hasn't changed
     */
    public $force = false;

    /**
     * @var string|int
     */
    public $developer;

    /**
     * @var bool Whether this is a managed package or just a dependency of a managed package
     */
    public $managed = false;

    /**
     * @var string The package's VCS repository URL
     */
    public $repository;

    /**
     * @var string The Composer package type
     */
    public $type = 'library';

    /**
     * @var bool Whether the action should be added to the queue
     */
    public $queue = false;

    /**
     * @var string The specific version to update.
     */
    public $version;

    /**
     * @var bool Whether the Composer repository JSON files should be regenerated after the action is complete
     */
    public $dumpJson = false;

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        // Convert kebab-case names to camelCase
        if (strpos($name, '-') !== false) {
            /** @noinspection PhpUndefinedClassInspection */
            $name = lcfirst(Inflector::id2camel($name));
            return $this->$name;
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        // Convert kebab-case names to camelCase
        if (strpos($name, '-') !== false) {
            /** @noinspection PhpUndefinedClassInspection */
            $name = lcfirst(Inflector::id2camel($name));
            $this->$name = $value;
            return;
        }
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'dumpJson';

        switch ($actionID) {
            case 'add':
                $options[] = 'developer';
                $options[] = 'managed';
                $options[] = 'repository';
                $options[] = 'type';
                break;
            case 'update':
                $options[] = 'force';
                $options[] = 'queue';
                $options[] = 'version';
                break;
            case 'update-deps':
            case 'update-managed-packages':
                $options[] = 'force';
                $options[] = 'queue';
                break;
            case 'create-webhook':
            case 'create-all-webhooks':
                $options[] = 'force';
                break;
            case 'process-changelog':
                $options[] = 'version';
                break;
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        $aliases = parent::optionAliases();
        $aliases['f'] = 'force';
        $aliases['m'] = 'managed';
        $aliases['r'] = 'repository';
        $aliases['t'] = 'type';
        $aliases['q'] = 'queue';
        return $aliases;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        if ($this->action->id !== 'update' && $this->dumpJson) {
            $this->module->getJsonDumper()->dump($this->queue);
        }

        return parent::afterAction($action, $result);
    }

    /**
     * Adds a new Composer package.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionAdd(string $name): int
    {
        if ($this->developer) {
            try {
                $developer = $this->_developer($this->developer);
            } catch (InvalidArgumentException $e) {
                Console::error(Console::ansiFormat($e->getMessage(), [Console::FG_RED]));
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $packageManager = $this->module->getPackageManager();
        $package = new Package([
            'developerId' => $developer->id ?? null,
            'name' => $name,
            'type' => $this->type,
            'repository' => $this->repository,
            'managed' => $this->managed,
        ]);
        $packageManager->savePackage($package);
        Console::output("Done adding {$name}");
        if ($this->confirm('Update its versions now?')) {
            $packageManager->updatePackage($name);
        }
        if (!$this->dumpJson && $this->confirm('Dump new Composer JSON?')) {
            $this->module->getJsonDumper()->dump();
        }

        return ExitCode::OK;
    }

    /**
     * Assigns a package to a developer.
     *
     * @param string $name The package name
     * @param int|string $developer The developer's ID, username, or email
     * @param string $repository The package's repository URL
     * @return int
     */
    public function actionAssign(string $name, $developer, string $repository): int
    {
        $packageManager = $this->module->getPackageManager();
        try {
            $package = $packageManager->getPackage($name);
            $developer = $this->_developer($developer);
        } catch (InvalidArgumentException $e) {
            Console::error(Console::ansiFormat($e->getMessage(), [Console::FG_RED]));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $package->developerId = $developer->id;
        $package->managed = true;
        $package->repository = $repository;
        $packageManager->savePackage($package);
        Console::output("Done assigning {$name} to {$developer->email}.");

        if ($package->webhookId) {
            $create = $this->confirm('Recreate a webhook?');
        } else {
            $create = $this->confirm('Create a webhook?');
        }

        if ($create) {
            $packageManager->createWebhook($name, true);
        }

        return ExitCode::OK;
    }

    /**
     * Removes a Composer package.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionRemove(string $name): int
    {
        $packageManager = $this->module->getPackageManager();
        try {
            $package = $packageManager->getPackage($name);
        } catch (InvalidArgumentException $e) {
            Console::error(Console::ansiFormat($e->getMessage(), [Console::FG_RED]));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->confirm("Are you sure you want to remove {$name}?")) {
            $packageManager->removePackage($package);
            Console::output("{$name} removed.");
        }

        return ExitCode::OK;
    }

    /**
     * Updates our version records for a Composer package.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionUpdate(string $name): int
    {
        $this->module->getPackageManager()->updatePackage($name, $this->force, $this->queue, $this->dumpJson, $this->version);
        return ExitCode::OK;
    }

    /**
     * Updates our version records for all non-managed Composer packages.
     *
     * @return int
     */
    public function actionUpdateDeps(): int
    {
        $this->module->getPackageManager()->updateDeps($this->force, $this->queue, $errors);

        if (!empty($errors)) {
            $this->stderr('Done, but encountered the following errors:' . PHP_EOL, Console::FG_RED);
            foreach ($errors as $packageName => $packageErrors) {
                $this->stderr("* {$packageName}:" . PHP_EOL, Console::FG_RED);
                foreach ($packageErrors as $error) {
                    $this->stderr("  - {$error}" . PHP_EOL, Console::FG_RED);
                }
            }
        } else {
            $this->stdout('Done' . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Updates our version records for all managed Composer packages.
     *
     * @return int
     */
    public function actionUpdateManagedPackages(): int
    {
        $this->module->getPackageManager()->updateManagedPackages($this->force, $this->queue, $errors);

        if (!empty($errors)) {
            $this->stderr('Done, but encountered the following errors:' . PHP_EOL, Console::FG_RED);
            foreach ($errors as $packageName => $packageErrors) {
                $this->stderr("* {$packageName}:" . PHP_EOL, Console::FG_RED);
                foreach ($packageErrors as $error) {
                    $this->stderr("  - {$error}" . PHP_EOL, Console::FG_RED);
                }
            }
        } else {
            $this->stdout('Done' . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Creates a VCS webhook for a given package.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionCreateWebhook(string $name): int
    {
        $this->module->getPackageManager()->createWebhook($name, $this->force);
        return ExitCode::OK;
    }

    /**
     * Deletes a VCS webhook for a given package.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionDeleteWebhook(string $name): int
    {
        $this->module->getPackageManager()->deleteWebhook($name);
        return ExitCode::OK;
    }

    /**
     * Creates new webhooks for all managed packages.
     *
     * @return int
     */
    public function actionCreateAllWebhooks(): int
    {
        $packageManager = $this->module->getPackageManager();
        $names = $packageManager->getPackageNames();

        foreach ($names as $name) {
            $package = $packageManager->getPackage($name);
            if ($package->managed) {
                $packageManager->createWebhook($package, $this->force);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Deletes webhooks for all managed packages.
     *
     * @return int
     */
    public function actionDeleteAllWebhooks(): int
    {
        $packageManager = $this->module->getPackageManager();
        $names = $packageManager->getPackageNames();

        foreach ($names as $name) {
            $package = $packageManager->getPackage($name);
            if ($package->managed) {
                $packageManager->deleteWebhook($package);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Updates a package's versions' release dates and critical flags based on its latest changelog.
     *
     * @param string $name The package name
     * @return int
     */
    public function actionProcessChangelog(string $name): int
    {
        $this->_processChangelog($name, $this->version);
        return ExitCode::OK;
    }

    /**
     * Updates all packages' versions' release dates and critical flags based on their latest changelogs.
     *
     * @return int
     */
    public function actionProcessAllChangelogs(): int
    {
        $packageManager = $this->module->getPackageManager();
        $names = $packageManager->getPackageNames();
        $total = count($names);

        foreach ($names as $i => $name) {
            $this->stdout("[$i/$total] ");
            $this->_processChangelog($name);
        }

        $this->stdout('Finished processing changelogs.' . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function _processChangelog(string $name, ?string $version = null)
    {
        $this->stdout('Processing ');
        $this->stdout($name, Console::FG_CYAN);
        $this->stdout(' ... ');
        $this->module->getPackageManager()->processPackageChangelog($name, $version);
        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * Returns a developer by its ID, email, or username.
     *
     * @param mixed $developer
     * @return User
     * @throws InvalidArgumentException
     */
    private function _developer($developer): User
    {
        if ($developer instanceof User) {
            return $developer;
        }

        if (is_numeric($developer)) {
            $user = User::findOne($developer);
        } else {
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($developer);
        }

        if (!$user) {
            throw new InvalidArgumentException('Unknown developer: ' . $developer);
        }

        return $user;
    }
}
