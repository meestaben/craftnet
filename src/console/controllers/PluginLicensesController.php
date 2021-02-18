<?php

namespace craftnet\console\controllers;

use Composer\Semver\VersionParser;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craftnet\errors\LicenseNotFoundException;
use craftnet\helpers\KeyHelper;
use craftnet\Module;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginLicense;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Markdown;
use yii\validators\EmailValidator;

/**
 * Manages plugin licenses.
 *
 * @property Module $module
 */
class PluginLicensesController extends Controller
{
    /**
     * Claims licenses for the user with the given username or email.
     */
    public function actionCreate(): int
    {
        $license = new PluginLicense();

        $plugin = null;
        $edition = null;

        $plugin = $this->_pluginPrompt();
        $edition = $this->_pluginEditionPrompt($plugin);

        $license->pluginHandle = $plugin->handle;
        $license->edition = $edition->handle;

        $cmsLicenseKey = $this->prompt('Craft license key (optional):', [
            'validator' => function(string $input, string &$error = null) {
                try {
                    $this->module->getCmsLicenseManager()->getLicenseByKey($input);
                    return true;
                } catch (LicenseNotFoundException $e) {
                    $error = $e->getMessage();
                    return false;
                }
            }
        ]);

        if ($cmsLicenseKey) {
            $license->cmsLicenseId = $this->module->getCmsLicenseManager()->getLicenseByKey($cmsLicenseKey)->id;
        }

        $license->email = $this->prompt('Owner email:', [
            'required' => true,
            'validator' => function(string $email, string &$error = null) {
                return (new EmailValidator())->validate($email, $error);
            }
        ]);

        if ($license->expirable = $this->confirm('Expirable?')) {
            $license->expiresOn = DateTimeHelper::toDateTime($this->prompt('Expiration date:', [
                'required' => true,
                'validator' => function(string $input) {
                    return DateTimeHelper::toDateTime($input) !== false;
                },
                'default' => (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+1 year')->format('Y-m-d'),
            ]), false, false);
            $license->autoRenew = $this->confirm('Auto-renew?');
        }

        $license->notes = $this->prompt('Owner-facing notes:') ?: null;
        $license->privateNotes = $this->prompt('Private notes:') ?: null;

        $license->key = $this->prompt('License key (optional):', [
            'validator' => function(string $input, string &$error = null) {
                try {
                    $this->module->getPluginLicenseManager()->normalizeKey($input);
                    return true;
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    return false;
                }
            }
        ]) ?: KeyHelper::generatePluginKey();

        $license->pluginId = $plugin->id;
        $license->editionId = $edition->id;
        $license->ownerId = User::find()->select(['elements.id'])->email($license->email)->scalar() ?: null;
        $license->expired = $license->expiresOn !== null ? $license->expiresOn->getTimestamp() < time() : false;

        if (!$this->module->getPluginLicenseManager()->saveLicense($license)) {
            $this->stderr('Could not save license: ' . implode(', ', $license->getFirstErrors()) . PHP_EOL, Console::FG_RED);
            return 1;
        }

        $this->stdout('License saved: ' . $license->key . PHP_EOL, Console::FG_GREEN);

        if ($this->confirm('Associate the license with an order?')) {
            $orderNumber = $this->prompt('Order number:', [
                'required' => true,
                'validator' => function(string $input) {
                    return Order::find()->number($input)->exists();
                }
            ]);
            $order = Order::find()->number($orderNumber)->one();
            /** @var LineItem[] $lineItems */
            $lineItems = [];
            $lineItemOptions = [];
            foreach ($order->getLineItems() as $i => $lineItem) {
                $key = (string)($i + 1);
                $lineItems[$key] = $lineItem;
                $lineItemOptions[$key] = $lineItem->getDescription();
            }
            $key = $this->select('Which line item?', $lineItemOptions);
            $lineItem = $lineItems[$key];
            Craft::$app->getDb()->createCommand()
                ->insert('craftnet_pluginlicenses_lineitems', [
                    'licenseId' => $license->id,
                    'lineItemId' => $lineItem->id,
                ], false)
                ->execute();
        }

        if ($this->confirm('Create a history record for the license?', true)) {
            $note = $this->prompt('Note: ', [
                'required' => true,
                'default' => "created by {$license->email}" . (isset($order) ? " per order {$order->number}" : '')
            ]);
            $this->module->getPluginLicenseManager()->addHistory($license->id, $note);
        }

        return 0;
    }

    /**
     * Upgrades Lite edition licenses to Pro based on the existence of an old "Pro" plugin license.
     *
     * @return int
     */
    public function actionUpgrade(): int
    {
        getPluginHandle:
        $pluginHandle = $this->prompt('Plugin handle:', ['required' => true]);
        $plugin = Plugin::find()->handle($pluginHandle)->one();
        if (!$plugin) {
            $this->stdout('Invalid handle' . PHP_EOL, Console::FG_RED);
            goto getPluginHandle;
        }

        $liteEdition = $plugin->getEdition('lite');
        $proEdition = $plugin->getEdition('pro');

        getOldPluginHandle:
        $oldPluginHandle = $this->prompt('Old "pro" plugin handle:', ['required' => true, 'default' => $pluginHandle . '-pro']);
        $oldPlugin = Plugin::find()->anyStatus()->handle($oldPluginHandle)->one();
        if (!$oldPlugin) {
            $this->stdout('Invalid handle' . PHP_EOL, Console::FG_RED);
            goto getOldPluginHandle;
        }

        $oldEdition = $oldPlugin->getEdition('standard');

        $version = $this->prompt('Plugin version that added edition support:', [
            'required' => true,
            'validator' => function(string $input) {
                try {
                    (new VersionParser())->normalize($input);
                    return true;
                } catch (\UnexpectedValueException $e) {
                    return false;
                }
            },
            'error' => 'Invalid version',
        ]);

        // Make sure that all old pro licenses have a Lite edition license for the same Craft site,
        // and owned by the same Craft ID account

        $manager = $this->module->getPluginLicenseManager();

        $licenseQuery = (new Query())
            ->select([
                'id' => 'old.id',
                'ownerId' => 'old.ownerId',
                'cmsLicenseId' => 'old.cmsLicenseId',
            ])
            ->from('craftnet_pluginlicenses old')
            ->where(['old.editionId' => $oldEdition->id]);

        if ($liteEdition->price != 0) {
            $licenseQuery
                ->addSelect([
                    'liteId' => 'lite.id',
                ])
                ->leftJoin('craftnet_pluginlicenses lite', [
                    'and',
                    ['lite.editionId' => $liteEdition->id],
                    '[[lite.cmsLicenseId]] = [[old.cmsLicenseId]]',
                ]);

            $badLicenses = (clone $licenseQuery)
                ->addSelect([
                    'liteOwnerId' => 'lite.ownerId',
                ])
                ->andWhere([
                    'or',
                    ['lite.id' => null],
                    ['old.ownerId' => null],
                    ['old.cmsLicenseId' => null],
                    '[[old.ownerId]] != [[lite.ownerId]]',
                ])
                ->all();

            if (!empty($badLicenses)) {
                $this->stderr('The following licenses need to be dealt with first:' . PHP_EOL, Console::FG_RED);
                foreach ($badLicenses as $result) {
                    $license = $manager->getLicenseById($result['id']);
                    $errors = [];
                    if (!$result['cmsLicenseId']) {
                        $errors[] = 'not attached to a Craft license';
                    } else if (!$result['liteId']) {
                        $errors[] = 'no lite license found';
                    }
                    if (!$result['ownerId'] || ($result['liteId'] && !$result['liteOwnerId'])) {
                        if (!$result['ownerId']) {
                            $errors[] = 'no owner';
                        }
                        if (!$result['liteOwnerId']) {
                            $errors[] = 'no lite owner';
                        }
                    } else if ($result['liteId'] && $result['ownerId'] != $result['liteOwnerId']) {
                        $errors[] = 'owner mismatch';
                    }
                    $this->stderr("- {$license->key} (" . implode(', ', $errors) . ')' . PHP_EOL, Console::FG_RED);
                }
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $licenses = $licenseQuery->all();
        $this->stdout('Upgrading ' . count($licenses) . ' licenses ...' . PHP_EOL, Console::FG_YELLOW);
        $mailer = Craft::$app->getMailer();

        foreach ($licenses as $result) {
            $oldLicense = $manager->getLicenseById($result['id']);

            if ($liteEdition->price != 0) {
                // Upgrade the lite license to the Pro edition
                $liteLicense = $manager->getLicenseById($result['liteId']);
                $this->stdout("- {$oldLicense->key} ({$oldPlugin->name} - Standard) => {$liteLicense->key} ({$plugin->name} - Pro) ... ", Console::FG_YELLOW);

                $liteLicense->editionId = $proEdition->id;

                if ($liteLicense->expirable && $oldLicense->expirable) {
                    // Go with whatever the greater expiry date is
                    $liteLicense->expiresOn = max($oldLicense->expiresOn, $liteLicense->expiresOn);
                }

                // Disable auto-renew if the old pro license didn't have it enabled
                if (!$oldLicense->autoRenew) {
                    $liteLicense->autoRenew = false;
                }

                $manager->saveLicense($liteLicense, false);
                $manager->addHistory($liteLicense->id, "Upgraded to Pro edition per old {$oldPlugin->name} license ({$oldLicense->key})");

                // Delete the old license
                $manager->deleteLicenseById($oldLicense->id);
            } else {
                // Reassign the license to the Pro edition
                $this->stdout("- {$oldLicense->key} ({$oldPlugin->name} - Standard => {$plugin->name} - Pro) ... ", Console::FG_YELLOW);
                $oldLicense->pluginId = $plugin->id;
                $oldLicense->pluginHandle = $plugin->handle;
                $oldLicense->editionId = $proEdition->id;
                $manager->saveLicense($oldLicense, false);
                $manager->addHistory($oldLicense->id, "Reassigned to {$plugin->name} (new Pro edition)");
            }

            // Send the notification email
            if ($liteEdition->price != 0) {
                /** @noinspection PhpUndefinedVariableInspection */
                $owner = User::findOne($liteLicense->ownerId);
                $editUrl = $liteLicense->getEditUrl();
            } else {
                $owner = $oldLicense->ownerId ? User::findOne($oldLicense->ownerId) : null;
                $editUrl = $oldLicense->getEditUrl();
            }

            $name = ($owner->firstName ?? null) ?: 'there';
            $body = <<<EOD
Hi {$name},

{$plugin->name} {$version} was just released, with built-in Lite and Pro editions. That means that there’s no longer any
need to install the {$oldPlugin->name} plugin separately.


EOD;
            if ($liteEdition->price != 0) {
                /** @noinspection PhpUndefinedVariableInspection */
                $body .= <<<EOD
We’ve gone ahead and upgraded your {$plugin->name} license ([`{$liteLicense->shortKey}`]($editUrl)) to the new Pro
edition, since you had a {$oldPlugin->name} license (`{$oldLicense->shortKey}`) tied to the same Craft project.


EOD;
            } else {
                $body .= <<<EOD
We’ve gone ahead and reassigned your {$oldPlugin->name} license ([`{$oldLicense->shortKey}`]($editUrl)) to the new Pro
edition of {$plugin->name}. Please go to the Settings → Plugins page in your Control Panel and enter your
{$oldPlugin->name} license key into the {$plugin->name} license key input.


EOD;
            }

            $body .= <<<EOD
After you update to {$plugin->name} {$version} or later, go to the Settings → Plugins page in your Control Panel and
switch {$plugin->name} over to the Pro edition. Then you can uninstall the old {$oldPlugin->name} plugin.

Let us know if you have any questions.

Have a good day!
EOD;

            $mailer->compose()
                ->setTo($owner ?? $oldLicense->email)
                ->setSubject("Your {$plugin->name} license")
                ->setTextBody($body)
                ->setHtmlBody(Markdown::process($body))
                ->send();

            $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
        }

        $this->stdout('Done upgrading licenses' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Transfers all licenses from one plugin/edition to another.
     *
     * @return int
     */
    public function actionTransfer(): int
    {
        $oldPlugin = $this->_pluginPrompt('Old plugin:');
        $oldEdition = $this->_pluginEditionPrompt($oldPlugin, 'Old edition:');
        $newPlugin = $this->_pluginPrompt('New plugin:');
        $newEdition = $this->_pluginEditionPrompt($newPlugin, 'New edition:');

        if ($oldEdition->id == $newEdition->id) {
            $this->stdout("That’s the same plugin/edition. Guess we’re done!\n");
            return ExitCode::OK;
        }

        $licenseManager = $this->module->getPluginLicenseManager();
        $allLicenses = $licenseManager->getLicensesByPlugin($oldPlugin->id, $oldEdition->id, true);

        if (empty($allLicenses)) {
            $this->stdout("No licenses found for $oldEdition->description.\n");
            return ExitCode::OK;
        }

        $this->stdout(count($allLicenses), Console::FG_CYAN);
        $this->stdout(" $oldEdition->description licenses found.\n");

        $sendEmail = $this->confirm('Send email to license holders?');

        if (!$this->confirm('Transfer licenses now?')) {
            $this->stdout("Aborted\n");
            return ExitCode::OK;
        }

        $licensesByEmail = ArrayHelper::index($allLicenses, null, function(PluginLicense $license): string {
            return mb_strtolower($license->email);
        });

        $mailer = Craft::$app->getMailer();

        foreach ($licensesByEmail as $email => $licenses) {
            foreach ($licenses as $license) {
                $this->stdout("Transferring $license->shortKey ($license->email) ... ");
                $license->pluginId = $newPlugin->id;
                $license->pluginHandle = $newPlugin->handle;
                $license->editionId = $newEdition->id;
                $license->edition = $newEdition->handle;

                if (!$licenseManager->saveLicense($license)) {
                    $errors = implode('', array_map(function(string $error) {
                        return " - $error\n";
                    }, $license->getFirstErrors()));
                    $this->stdout("validation errors:\n$errors", Console::FG_RED);
                    continue 2;
                }

                $licenseManager->addHistory($license->id, "Transferred from {$oldEdition->getDescription()}");
                $this->stdout("done\n", Console::FG_GREEN);
            }

            if ($sendEmail) {
                $this->stdout('- Sending email ... ');
                $user = User::find()->email($email)->one();
                $message = $mailer
                    ->composeFromKey(Module::MESSAGE_KEY_LICENSE_TRANSFER, compact(
                        'oldPlugin',
                        'oldEdition',
                        'newPlugin',
                        'newEdition',
                        'user',
                        'licenses'
                    ))
                    ->setTo($user ?? $email);

                if ($message->send()) {
                    $this->stdout("done\n", Console::FG_GREEN);
                } else {
                    $this->stderr("error sending email\n", Console::FG_RED);
                }
            }
        }

        return ExitCode::OK;
    }

    /**
     * Prompts for a plugin.
     *
     * @param string $text
     * @return Plugin
     */
    private function _pluginPrompt(string $text = 'Plugin:'): Plugin
    {
        $handle = $this->prompt($text, [
            'required' => true,
            'validator' => function(string $input, string &$error = null) {
                if (!Plugin::find()->handle($input)->exists()) {
                    $error = 'No plugin exists with that handle.';
                    return false;
                }
                return true;
            }
        ]);

        return Plugin::find()->handle($handle)->one();
    }

    /**
     * Prompts for a plugin edition.
     *
     * @param Plugin $plugin
     * @param string $text
     * @return PluginEdition
     */
    private function _pluginEditionPrompt(Plugin $plugin, string $text = 'Edition:'): PluginEdition
    {
        $editions = PluginEdition::find()->pluginId($plugin->id)->indexBy('name')->all();

        if (empty($editions)) {
            throw new InvalidArgumentException("$plugin->name has no editions");
        }

        if (count($editions) === 1) {
            return reset($editions);
        }

        $name = $this->prompt($text, [
            'required' => true,
            'options' => array_keys($editions),
        ]);

        return $editions[$name];
    }
}
