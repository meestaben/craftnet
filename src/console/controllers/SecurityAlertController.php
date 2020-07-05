<?php

namespace craftnet\console\controllers;

use Composer\Semver\Comparator;
use Craft;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craftnet\Module;
use craftnet\plugins\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Alert customers about a critical update
 *
 * @property Module $module
 */
class SecurityAlertController extends Controller
{
    /**
     * Alerts customers about a critical update
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $handle = $this->prompt('Plugin:', [
            'required' => true,
            'validator' => function(string $input, string &$error = null) {
                if (!Plugin::find()->handle($input)->exists()) {
                    $error = 'No plugin exists with that handle.';
                    return false;
                }
                return true;
            }
        ]);

        $plugin = Plugin::find()->handle($handle)->one();

        $fixedVersion = $this->prompt('What version fixed the issue?', [
            'required' => true,
        ]);

        getRelease:
        $release = $this->module->getPackageManager()->getRelease($plugin->packageName, $fixedVersion);

        if (!$release) {
            $this->stderr('Invalid version' . PHP_EOL, Console::FG_RED);
            goto getRelease;
        }

        $this->stdout('Looking for vulnerable licenses ... ');

        $licenses = $this->module->getPluginLicenseManager()->getLicensesByPlugin($plugin->id);
        $vulnerableLicensesByUserId = [];
        $vulnerableLicensesByEmail = [];
        $count = 0;

        foreach ($licenses as $license) {
            if (Comparator::lessThan($license->lastVersion, $fixedVersion)) {
                if ($license->ownerId) {
                    $vulnerableLicensesByUserId[$license->ownerId][] = $license;
                } else {
                    $vulnerableLicensesByEmail[mb_strtolower($license->email)][] = $license;
                }
                $count++;
            }
        }

        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        $uniques = count($vulnerableLicensesByUserId) + count($vulnerableLicensesByEmail);
        $this->stdout("Found $count vulnerable licenses for $uniques unique user accounts/emails" . PHP_EOL);

        if (!$count || !$this->confirm('Send security alert emails now?')) {
            return ExitCode::OK;
        }

        $mailer = Craft::$app->getMailer();
        $i = 0;

        foreach ($vulnerableLicensesByUserId as $userId => $licenses) {
            $i++;
            $user = User::findOne($userId);
            $to = $user ? "$user->username ($user->email)" : reset($licenses)->email;
            $this->stdout("    - [$i/$uniques] Emailing {$to} about " . count($licenses) . ' licenses ... ');
            $message = $mailer->composeFromKey(Module::MESSAGE_KEY_SECURITY_ALERT, [
                'user' => $user,
                'name' => $plugin->name,
                'release' => $release,
                'licenses' => $licenses,
            ])->setTo($user ?? reset($licenses)->email)->setPriority(1);
            if ($message->send()) {
                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
            } else {
                $this->stderr('error sending email' . PHP_EOL, Console::FG_RED);
            }
        }

        foreach ($vulnerableLicensesByEmail as $email => $licenses) {
            $i++;
            $message = $mailer->composeFromKey(Module::MESSAGE_KEY_SECURITY_ALERT, [
                'name' => $plugin->name,
                'release' => $release,
                'licenses' => $licenses,
            ])->setTo($email)->setPriority(1);
            $this->stdout("    - [$i/$uniques] Emailing {$email} about " . count($licenses) . ' licenses ... ');
            if ($message->send()) {
                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
            } else {
                $this->stderr('error sending email' . PHP_EOL, Console::FG_RED);
            }
        }

        return ExitCode::OK;
    }
}
