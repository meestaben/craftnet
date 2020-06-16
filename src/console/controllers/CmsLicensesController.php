<?php

namespace craftnet\console\controllers;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craftnet\behaviors\UserBehavior;
use craftnet\cms\CmsLicense;
use craftnet\errors\LicenseNotFoundException;
use craftnet\helpers\KeyHelper;
use craftnet\Module;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\validators\EmailValidator;

/**
 * Manages Craft licenses.
 *
 * @property Module $module
 */
class CmsLicensesController extends Controller
{
    /**
     * Creates a new Craft license.
     */
    public function actionCreate(): int
    {
        $edition = null;
        $note = null;
        $expiresOn = null;
        $autoRenew = null;

        $quantity = $this->quantityPrompt('1');

        $editionHandle = $this->editionHandlePrompt('pro');
        $email = $this->emailPrompt();

        if ($expirable = $this->confirm('Expirable?', true)) {
            $expiresOn = $this->expiresOnPrompt();
            $autoRenew = $this->confirm('Auto-renew?');
        }

        $notes = $this->prompt('Owner-facing notes:') ?: null;
        $privateNotes = $this->prompt('Private notes:') ?: null;
        $ownerId = User::find()->select(['elements.id'])->email($email)->scalar() ?: null;
        $expired = $expiresOn !== null ? $expiresOn->getTimestamp() < time() : false;

        if ($this->confirm('Create a history record for the license?', true)) {
            $note = $this->prompt('Note: ', [
                'required' => true,
                'default' => "created by {$email}"
            ]);
        }

        for ($counter = 1; $counter <= $quantity; $counter++) {
            $key = KeyHelper::generateCmsKey();

            $license = new CmsLicense();
            $license->key = $key;
            $license->editionHandle = $editionHandle;
            $license->email = $email;
            $license->expirable = $expirable;
            $license->notes = $notes;
            $license->privateNotes = $privateNotes;
            $license->ownerId = $ownerId;
            $license->expired = $expired;

            if ($expirable) {
                $license->expiresOn = $expiresOn;
                $license->autoRenew = $autoRenew;
            }

            if (!$this->module->getCmsLicenseManager()->saveLicense($license)) {
                $this->stderr('Could not save license: ' . implode(', ', $license->getFirstErrors()) . PHP_EOL, Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("License #{$counter} saved: " . PHP_EOL . chunk_split($license->key, 50) . PHP_EOL, Console::FG_GREEN);

            if ($note) {
                $this->module->getCmsLicenseManager()->addHistory($license->id, $note);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Looks up a license.
     *
     * @param string $key The license key (or first few characters of it)
     * @return int
     */
    public function actionLookup(string $key): int
    {
        try {
            $license = $this->license($key);
        } catch (LicenseNotFoundException $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->info('Edition', $license->editionHandle);
        $this->boolean('Expirable', $license->expirable);
        $this->boolean('Expired', $license->expired);
        $this->datetime($license->expired ? 'Expired on' : 'Expires on', $license->expiresOn);
        $this->boolean('Auto-renew', $license->autoRenew);
        $this->info('Email', $license->email);
        $this->info('Domain', $license->domain ?: '--');
        $this->info('Notes', $license->notes ?: '--');
        $this->info('Private notes', $license->privateNotes ?: '--');
        $this->info('Last edition', $license->lastEdition ?: '--');
        $this->info('Last version', $license->lastVersion ?: '--');
        $this->info('Last allowed version', $license->lastAllowedVersion ?: '--');
        $this->datetime('Last activity on', $license->lastActivityOn);
        $this->datetime('Last renewed on', $license->lastRenewedOn);

        if ($license->ownerId) {
            $owner = User::find()
                ->id($license->ownerId)
                ->anyStatus()
                ->one();

            $this->info('Owner ID', $license->ownerId);
            $this->info('Owner status', $owner ? $owner->getStatus() : Console::ansiFormat('nonexistent', [Console::FG_RED]));

            if ($owner) {
                /** @var UserBehavior|User $owner */
                $this->info('Owner email', $owner->email);
                $this->info('Owner username', $owner->username);
                $this->info('Owner name', $owner->developerName ?: ($owner->getFullName() ?: '--'));
            }
        }

        return ExitCode::OK;
    }

    /**
     * Updates an existing Craft license.
     *
     * @param string $key The license key (or first few characters of it)
     * @return int
     */
    public function actionUpdate(string $key): int
    {
        try {
            $license = $this->license($key);
        } catch (LicenseNotFoundException $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $edition = null;

        $license->editionHandle = $this->editionHandlePrompt($license->editionHandle);
        $license->editionId = null;
        $license->email = $this->emailPrompt($license->email);

        if ($license->expirable = $this->confirm('Expirable?', $license->expirable)) {
            $license->expiresOn = $this->expiresOnPrompt($license->expiresOn);
            $license->autoRenew = $this->confirm('Auto-renew?', $license->autoRenew);
        }

        $license->notes = $this->prompt('Owner-facing notes:', [
            'default' => $license->notes,
        ]) ?: null;
        $license->privateNotes = $this->prompt('Private notes:', [
            'default' => $license->privateNotes,
        ]) ?: null;

        $license->expired = $license->expiresOn !== null ? $license->expiresOn->getTimestamp() < time() : false;

        if (!$this->module->getCmsLicenseManager()->saveLicense($license)) {
            $this->stderr('Could not save license: ' . implode(', ', $license->getFirstErrors()) . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('License saved: ' . PHP_EOL . chunk_split($license->key, 50) . PHP_EOL, Console::FG_GREEN);

        if ($this->confirm('Create a history record for the license?', true)) {
            $note = $this->prompt('Note: ', [
                'required' => true,
            ]);
            $this->module->getCmsLicenseManager()->addHistory($license->id, $note);
        }

        return ExitCode::OK;
    }

    /**
     * @param string $key
     * @return CmsLicense
     * @throws LicenseNotFoundException
     */
    protected function license(string $key): CmsLicense
    {
        $manager = $this->module->getCmsLicenseManager();

        try {
            $key = $manager->normalizeKey($key);
        } catch (InvalidArgumentException $e) {
            $licenses = (new Query())
                ->select(['key', 'domain'])
                ->from(['craftnet_cmslicenses'])
                ->where(['like', 'key', $key . '%', false])
                ->all();

            if (empty($licenses)) {
                throw new LicenseNotFoundException('No Craft licenses exist with a key that starts with "' . $key . '".');
            }

            $options = [];
            $keys = [];
            foreach ($licenses as $i => $license) {
                $index = (string)($i + 1);
                $options[$index] = $license['key'] . ($license['domain'] ? "({$license['domain']})" : '');
                $keys[$index] = $license['key'];
            }
            $choice = $this->select('Which license key?', $options);
            $key = $keys[$choice];
        }

        return $manager->getLicenseByKey($key);
    }

    protected function info(string $label, string $value)
    {
        Console::output(Console::ansiFormat($label . ': ', [Console::FG_YELLOW]) . $value);
    }

    protected function boolean(string $label, $value)
    {
        $this->info($label, $value ? 'yes' : 'no');
    }

    protected function datetime(string $label, $value)
    {
        $this->info($label, $value ? Craft::$app->formatter->asDatetime($value) : '--');
    }

    protected function quantityPrompt(string $default = null): string
    {
        return $this->prompt('How many licenses?:', [
            'required' => true,
            'default' => $default,
            'validator' => function(string $quantity, string &$error = null) {
                if (!is_numeric($quantity) || (int)$quantity < 1) {
                    $error = 'Must be a number greater than 0.';
                    return false;
                }
                return true;
            }
        ]);
    }

    protected function editionHandlePrompt(string $default = null): string
    {
        return $this->prompt('Edition ("solo" or "pro"):', [
            'required' => true,
            'default' => $default,
            'validator' => function(string $edition, string &$error = null) {
                if (!in_array($edition, ['solo', 'pro'], true)) {
                    $error = 'Must be either "solo" or "pro".';
                    return false;
                }
                return true;
            }
        ]);
    }

    protected function emailPrompt(string $default = null): string
    {
        return $this->prompt('Owner email:', [
            'required' => true,
            'default' => $default,
            'validator' => function(string $email, string &$error = null) {
                return (new EmailValidator())->validate($email, $error);
            }
        ]);
    }

    protected function expiresOnPrompt(\DateTime $default = null): \DateTime
    {
        if ($default === null) {
            $default = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+1 year');
        }

        return DateTimeHelper::toDateTime($this->prompt('Expiration date:', [
            'required' => true,
            'validator' => function(string $input) {
                return DateTimeHelper::toDateTime($input) !== false;
            },
            'default' => $default->format('Y-m-d'),
        ]), false, false);
    }
}
