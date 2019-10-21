<?php

namespace craftnet\cms;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craftnet\errors\LicenseNotFoundException;
use craftnet\helpers\LicenseHelper;
use craftnet\helpers\OrderHelper;
use craftnet\Module;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use LayerShifter\TLDExtract\Extract;
use LayerShifter\TLDExtract\IDN;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\db\Expression;

class CmsLicenseManager extends Component
{
    // Constants
    // =========================================================================

    const EDITION_SOLO = 'solo';
    const EDITION_PRO = 'pro';

    // Properties
    // =========================================================================

    /**
     * @var array Domains that we treat as private, because they are only used for dev/testing/staging purposes
     * @see normalizeDomain()
     */
    public $devDomains = [];

    /**
     * @var array Domain suffixes that we consider to be public even though the Extract lib says they're private
     * @see normalizeDomain()
     */
    public $publicDomainSuffixes = [];

    /**
     * @var array Words that can be found in the subdomain that will cause the domain to be treated as private
     * @see normalizeDomain()
     */
    public $devSubdomainWords = [];

    // Public Methods
    // =========================================================================

    /**
     * Normalizes a license key by trimming whitespace and removing newlines.
     *
     * @param string $key
     * @return string
     * @throws InvalidArgumentException if $key is invalid
     */
    public function normalizeKey(string $key): string
    {
        $normalized = trim(preg_replace('/[\r\n]+/', '', $key));
        if (strlen($normalized) !== 250) {
            throw new InvalidArgumentException('Invalid license key: ' . $key);
        }

        return $normalized;
    }

    /**
     * Normalizes a public domain.
     *
     * @param string $url
     * @return string|null
     */
    public function normalizeDomain(string $url)
    {
        $isPunycoded = StringHelper::contains($url, 'xn--', false);

        if ($isPunycoded) {
            $url = (new IDN())->toUTF8($url);
        }

        $result = (new Extract(null, null, Extract::MODE_ALLOW_ICANN | Extract::MODE_ALLOW_PRIVATE))
            ->parse(mb_strtolower($url));

        if (($domain = $result->getRegistrableDomain()) === null) {
            return null;
        }

        // ignore if it's a private domain, unless we consider its suffix to be public (e.g. uk.com)
        if (!in_array($result->getSuffix(), $this->publicDomainSuffixes, true)) {
            $altDomain = (new Extract(null, null, Extract::MODE_ALLOW_ICANN))
                ->parse(mb_strtolower($url))
                ->getRegistrableDomain();
            if ($domain !== $altDomain) {
                return null;
            }
        }

        // ignore if it's a dev domain
        if (
            in_array($domain, $this->devDomains, true) ||
            in_array($result->getFullHost(), $this->devDomains, true)
        ) {
            return null;
        }

        // ignore if it's a nonstandard port
        $port = parse_url($url, PHP_URL_PORT);
        if ($port && $port != 80 && $port != 443) {
            return null;
        }

        // Check if any of the subdomains sound dev-y
        $subdomain = $result->getSubdomain();
        if ($subdomain && array_intersect(preg_split('/\b/', $subdomain), $this->devSubdomainWords)) {
            return null;
        }

        return $domain;
    }

    /**
     * Returns licenses that need to be renewed within the next 45 days.
     *
     * @param int $ownerId
     * @return CmsLicense[]
     * @throws \Exception
     */
    public function getRenewLicensesByOwner(int $ownerId): array
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->add(new \DateInterval('P45D'));

        $results = $this->_createLicenseQuery()
            ->where([
                'and',
                [
                    'l.ownerId' => $ownerId,
                    'l.editionHandle' => 'pro'
                ],
                [
                    'and',
                    ['<', 'expiresOn', Db::prepareDateForDb($date)]
                ]
            ])
            ->all();

        $licenses = [];

        foreach ($results as $result) {
            $licenses[] = new CmsLicense($result);
        }

        return $licenses;
    }

    /**
     * Returns licenses purchased by an order.
     *
     * @param int $orderId
     * @return CmsLicense[]]
     */
    public function getLicensesByOrder(int $orderId): array
    {
        $results = $this->_createLicenseQuery()
            ->innerJoin('craftnet_cmslicenses_lineitems l_li', '[[l_li.licenseId]] = [[l.id]]')
            ->innerJoin('commerce_lineitems li', '[[li.id]] = [[l_li.lineItemId]]')
            ->where(['li.orderId' => $orderId])
            ->all();

        $licenses = [];
        foreach ($results as $result) {
            $licenses[] = new CmsLicense($result);
        }

        return $licenses;
    }

    /**
     * Returns a license by its ID.
     *
     * @param int $id
     * @return CmsLicense
     * @throws LicenseNotFoundException if $id is missing
     */
    public function getLicenseById(int $id): CmsLicense
    {
        $result = $this->_createLicenseQuery()
            ->where(['l.id' => $id])
            ->one();

        if (!$result) {
            throw new LicenseNotFoundException($id);
        }

        return new CmsLicense($result);
    }

    /**
     * Returns a license by its key.
     *
     * @param string $key
     * @return CmsLicense
     * @throws LicenseNotFoundException if $key is missing
     */
    public function getLicenseByKey(string $key): CmsLicense
    {
        try {
            $key = $this->normalizeKey($key);
        } catch (InvalidArgumentException $e) {
            throw new LicenseNotFoundException($key, $e->getMessage(), 0, $e);
        }

        $result = $this->_createLicenseQuery()
            ->where(['l.key' => $key])
            ->one();

        if ($result === null) {
            throw new LicenseNotFoundException($key);
        }

        return new CmsLicense($result);
    }

    /**
     * Returns any licenses that are due to expire in the next 14-30 days and haven't been reminded about that yet.
     *
     * @return CmsLicense[]
     */
    public function getRemindableLicenses(): array
    {
        $rangeStart = (new \DateTime('midnight', new \DateTimeZone('UTC')))->modify('+14 days');
        $rangeEnd = (new \DateTime('midnight', new \DateTimeZone('UTC')))->modify('+30 days');

        $results = $this->_createLicenseQuery()
            ->where([
                'expirable' => true,
                'reminded' => false,
            ])
            ->andWhere(['between', 'expiresOn', Db::prepareDateForDb($rangeStart), Db::prepareDateForDb($rangeEnd)])
            ->all();

        $licenses = [];

        foreach ($results as $result) {
            $licenses[] = new CmsLicense($result);
        }

        return $licenses;
    }

    /**
     * Returns any licenses that have expired by today but don't know it yet.
     *
     * @return CmsLicense[]
     */
    public function getFreshlyExpiredLicenses(): array
    {
        $tomorrow = (new \DateTime('midnight', new \DateTimeZone('UTC')))->modify('+1 days');
        $results = $this->_createLicenseQuery()
            ->where([
                'expirable' => true,
                'expired' => false,
            ])
            ->andWhere(['not', ['expiresOn' => null]])
            ->andWhere(['<', 'expiresOn', Db::prepareDateForDb($tomorrow)])
            ->all();

        $licenses = [];

        foreach ($results as $result) {
            $licenses[] = new CmsLicense($result);
        }

        return $licenses;
    }

    /**
     * Saves a license.
     *
     * @param CmsLicense $license
     * @param bool $runValidation
     * @return bool if the license validated and was saved
     * @throws Exception if the license validated but didn't save
     * @throws \yii\db\Exception
     */
    public function saveLicense(CmsLicense $license, bool $runValidation = true): bool
    {
        if ($runValidation && !$license->validate()) {
            Craft::info('License not saved due to validation error.', __METHOD__);

            return false;
        }

        if (!$license->editionId) {
            $license->editionId = (int)CmsEdition::find()
                ->select('elements.id')
                ->handle($license->editionHandle)
                ->scalar();

            if ($license->editionId === false) {
                throw new Exception("Invalid Craft edition: {$license->editionHandle}");
            }
        }

        if ($license->expirable) {
            if (!$license->renewalPrice) {
                $license->renewalPrice = $license->getEdition()->getRenewal()->getPrice();
            }
        } else {
            $license->renewalPrice = null;
        }

        $data = [
            'editionId' => $license->editionId,
            'ownerId' => $license->ownerId,
            'expirable' => $license->expirable,
            'expired' => $license->expired,
            'autoRenew' => $license->autoRenew,
            'reminded' => $license->reminded,
            'renewalPrice' => $license->renewalPrice,
            'editionHandle' => $license->editionHandle,
            'email' => $license->email,
            'domain' => $license->domain,
            'key' => $license->key,
            'notes' => $license->notes,
            'privateNotes' => $license->privateNotes,
            'lastEdition' => $license->lastEdition,
            'lastVersion' => $license->lastVersion,
            'lastAllowedVersion' => $license->lastAllowedVersion,
            'lastActivityOn' => Db::prepareDateForDb($license->lastActivityOn),
            'lastRenewedOn' => Db::prepareDateForDb($license->lastRenewedOn),
            'expiresOn' => Db::prepareDateForDb($license->expiresOn),
            'dateCreated' => Db::prepareDateForDb($license->dateCreated),
        ];

        if (!$license->id) {
            $success = (bool)Craft::$app->getDb()->createCommand()
                ->insert('craftnet_cmslicenses', $data)
                ->execute();

            // set the ID an UID on the model
            $license->id = (int)Craft::$app->getDb()->getLastInsertID('craftnet_cmslicenses');
        } else {
            $success = (bool)Craft::$app->getDb()->createCommand()
                ->update('craftnet_cmslicenses', $data, ['id' => $license->id])
                ->execute();
        }

        if (!$success) {
            throw new Exception('License validated but didn’t save.');
        }

        return true;
    }

    /**
     * Adds a new record to a Craft license’s history.
     *
     * @param int $licenseId
     * @param string $note
     * @param string|null $timestamp
     */
    public function addHistory(int $licenseId, string $note, string $timestamp = null)
    {
        Craft::$app->getDb()->createCommand()
            ->insert('craftnet_cmslicensehistory', [
                'licenseId' => $licenseId,
                'note' => $note,
                'timestamp' => $timestamp ?? Db::prepareDateForDb(new \DateTime()),
            ], false)
            ->execute();
    }

    /**
     * Returns a license's history in chronological order.
     *
     * @param int $licenseId
     * @return array
     */
    public function getHistory(int $licenseId): array
    {
        return (new Query())
            ->select(['note', 'timestamp'])
            ->from('craftnet_cmslicensehistory')
            ->where(['licenseId' => $licenseId])
            ->orderBy(['timestamp' => SORT_ASC])
            ->all();
    }

    /**
     * Claims a license for a user.
     *
     * @param User $user
     * @param string $key
     *
     * @throws LicenseNotFoundException
     * @throws Exception
     */
    public function claimLicense(User $user, string $key)
    {
        $license = $this->getLicenseByKey($key);

        // make sure the license doesn't already have an owner
        if ($license->ownerId) {
            throw new Exception('License has already been claimed.');
        }

        $license->ownerId = $user->id;
        $license->email = $user->email;

        if (!$this->saveLicense($license)) {
            throw new Exception('Could not save Craft license: ' . implode(', ', $license->getErrorSummary(true)));
        }

        $this->addHistory($license->id, "claimed by {$user->email}");
    }

    /**
     * Finds unclaimed licenses that are associated the given user's email,
     * and and assigns them to the user.
     *
     * @param User $user
     * @param string|null $email the email to look for (defaults to the user's email)
     * @return int the total number of affected licenses
     */
    public function claimLicenses(User $user, string $email = null): int
    {
        return Craft::$app->getDb()->createCommand()
            ->update('craftnet_cmslicenses', [
                'ownerId' => $user->id,
            ], [
                'and',
                ['ownerId' => null],
                new Expression('lower([[email]]) = :email', [':email' => strtolower($email ?? $user->email)]),
            ], [], false)
            ->execute();
    }

    /**
     * Get licenses by owner.
     *
     * @param User $owner
     * @param string|null $searchQuery
     * @param $limit
     * @param $page
     * @param $orderBy
     * @param $ascending
     * @return array
     * @throws \Exception
     */
    public function getLicensesByOwner(User $owner, string $searchQuery = null, $limit, $page, $orderBy, $ascending): array
    {
        $defaultLimit = 30;

        $perPage = $limit ?? $defaultLimit;
        $offset = ($page - 1) * $perPage;

        $licenseQuery = $this->_createLicenseQueryForOwner($owner, $searchQuery);

        if ($orderBy) {
            $licenseQuery->orderBy([$orderBy => $ascending ? SORT_ASC : SORT_DESC]);
        }

        $licenseQuery
            ->offset($offset)
            ->limit($limit);

        $results = $licenseQuery->all();
        $resultsArray = [];
        foreach ($results as $result) {
            $resultsArray[] = new CmsLicense($result);
        }

        return $this->transformLicensesForOwner($resultsArray, $owner);
    }

    /**
     * Returns licenses by owner as an array.
     *
     * @param User $owner
     * @param string|null $searchQuery
     * @return int
     */
    public function getTotalLicensesByOwner(User $owner, string $searchQuery = null): int
    {
        $licenseQuery = $this->_createLicenseQueryForOwner($owner, $searchQuery);

        return $licenseQuery->count();
    }

    /**
     * Transforms licenses for the given owner.
     *
     * @param array $results
     * @param User $owner
     * @param array $include
     * @return array
     * @throws \Exception
     */
    public function transformLicensesForOwner(array $results, User $owner, array $include = []): array
    {
        $licenses = [];

        foreach ($results as $result) {
            $licenses[] = $this->transformLicenseForOwner($result, $owner, $include);
        }

        return $licenses;
    }

    /**
     * Transforms a license for the given owner.
     *
     * @param CmsLicense $result
     * @param User $owner
     * @param array $include
     * @return array
     * @throws \Exception
     */
    public function transformLicenseForOwner(CmsLicense $result, User $owner, array $include = []): array
    {
        if ($result->ownerId === $owner->id) {
            $license = $result->getAttributes(['id', 'key', 'domain', 'notes', 'email', 'autoRenew', 'expirable', 'expired', 'expiresOn', 'dateCreated']);
            $license['edition'] = $result->editionHandle;
        } else {
            $license = [
                'shortKey' => $result->getShortKey()
            ];
        }

        // History
        $license['history'] = $this->getHistory($result->id);

        // Edition details
        $license['editionDetails'] = CmsEdition::findOne($result->editionId);

        if (!empty($license['expiresOn'])) {
            $cmsLicenseExpiryDate = $license['expiresOn'];

            // CMS renewal options
            $renewalPrice = $license['editionDetails']->renewalPrice;
            $license['renewalOptions'] = $this->getRenewalOptions($cmsLicenseExpiryDate, $renewalPrice);
        }

        // Plugin Licenses
        if (in_array('pluginLicenses', $include, false)) {
            $pluginLicensesResults = Module::getInstance()->getPluginLicenseManager()->getLicensesByCmsLicenseId($result->id);
            $pluginLicenses = [];
            $license['pluginRenewalOptions'] = [];

            foreach ($pluginLicensesResults as $key => $pluginLicensesResult) {
                if ($pluginLicensesResult->ownerId === $owner->id) {
                    $pluginLicense = $pluginLicensesResult->getAttributes(['id', 'key', 'expired', 'expiresOn', 'autoRenew']);
                } else {
                    $pluginLicense = $pluginLicensesResult->getAttributes(['expiresOn', 'autoRenew']);
                    $pluginLicense['shortKey'] = $pluginLicensesResult->getShortKey();
                }

                // Edition details
                $pluginLicense['edition'] = PluginEdition::findOne($pluginLicensesResult->editionId);

                // Plugin details
                $plugin = null;

                if ($pluginLicensesResult->pluginId) {
                    $pluginResult = Plugin::find()->id($pluginLicensesResult->pluginId)->status(null)->one();
                    $plugin = $pluginResult->getAttributes(['name', 'handle']);
                }

                $pluginLicense['plugin'] = $plugin;

                $pluginLicenses[] = $pluginLicense;

                // Plugin renewal options
                if (isset($cmsLicenseExpiryDate)) {
                    $pluginHandle = $pluginLicense['edition']->getPlugin()->handle;
                    $pluginRenewalPrice = $pluginLicense['edition']->renewalPrice;
                    $pluginExpiryDate = $pluginLicense['expiresOn'];
                    $license['pluginRenewalOptions'][$pluginHandle] = $this->getRenewalOptions($pluginExpiryDate, $pluginRenewalPrice, $cmsLicenseExpiryDate);
                }
            }

            $license['pluginLicenses'] = $pluginLicenses;
        }

        return $license;
    }

    /**
     * Deletes a license by its key.
     *
     * @param string $key
     * @throws LicenseNotFoundException if $key is missing
     */
    public function deleteLicenseByKey(string $key)
    {
        try {
            $key = $this->normalizeKey($key);
        } catch (InvalidArgumentException $e) {
            throw new LicenseNotFoundException($key, $e->getMessage(), 0, $e);
        }

        $rows = Craft::$app->getDb()->createCommand()
            ->delete('craftnet_cmslicenses', ['key' => $key])
            ->execute();

        if ($rows === 0) {
            throw new LicenseNotFoundException($key);
        }
    }

    /**
     * Returns the number of licenses expiring in the next 45 days.
     *
     * @param User $owner
     * @return int
     * @throws \Exception
     */
    public function getExpiringLicensesTotal(User $owner): int
    {
        $date = new \DateTime('now');
        $date->add(new \DateInterval('P45D'));
        $dateFormatted = $date->format('Y-m-d');

        $licenseQuery = $this->_createLicenseQuery()
            ->where(['l.ownerId' => $owner->id])
            ->andWhere(['l.expired' => false])
            ->andWhere(['l.autoRenew' => false])
            ->andWhere(['not', ['l.expiresOn' => null]])
            ->andWhere(['<=', 'l.expiresOn', $dateFormatted]);

        return $licenseQuery->count();
    }

    // Private Methods
    // =========================================================================

    /**
     * @return Query
     */
    private function _createLicenseQuery(): Query
    {
        $query = (new Query())
            ->select([
                'l.id',
                'l.editionId',
                'l.ownerId',
                'l.expirable',
                'l.expired',
                'l.autoRenew',
                'l.reminded',
                'l.renewalPrice',
                'l.editionHandle',
                'l.email',
                'l.domain',
                'l.key',
                'l.notes',
                'l.privateNotes',
                'l.lastEdition',
                'l.lastVersion',
                'l.lastAllowedVersion',
                'l.lastActivityOn',
                'l.lastRenewedOn',
                'l.expiresOn',
                'l.dateCreated',
                'l.dateUpdated',
                'l.uid',
            ])
            ->from(['craftnet_cmslicenses l']);

        return $query;
    }

    /**
     * @param User $owner
     * @param string|null $searchQuery
     * @return Query
     */
    private function _createLicenseQueryForOwner(User $owner, string $searchQuery = null)
    {
        $query = $this->_createLicenseQuery($searchQuery)
            ->where(['l.ownerId' => $owner->id]);


        if ($searchQuery) {
            $query->andWhere([
                'or',
                ['ilike', 'l.key', $searchQuery],
                ['ilike', 'l.domain', $searchQuery],
                ['ilike', 'l.notes', $searchQuery],
                ['ilike', 'l.email', $searchQuery],
            ]);
        }

        return $query;
    }

    /**
     * @param \DateTime $licenseExpiryDate
     * @param float $renewalPrice
     * @param \DateTime|null $optionsExpiryDate
     * @return array
     * @throws \Exception
     */
    private function getRenewalOptions(\DateTime $licenseExpiryDate, float $renewalPrice, \DateTime $optionsExpiryDate = null): array
    {
        $licenseExpiryDate = new \DateTime($licenseExpiryDate->format('Y-m-d'), new \DateTimeZone('UTC'));

        if (!$optionsExpiryDate) {
            $optionsExpiryDate = $licenseExpiryDate;
        }

        $expiryDateOptions = LicenseHelper::getExpiryDateOptions($optionsExpiryDate);
        $renewalOptions = [];

        foreach ($expiryDateOptions as $key => $expiryDateOption) {
            $nextYear = OrderHelper::expiryStr2Obj($expiryDateOption[1]);
            $paidRenewalYears = OrderHelper::dateDiffInYears($nextYear, $licenseExpiryDate);
            $amount = 0;

            if ($nextYear > $licenseExpiryDate) {
                $amount = round($renewalPrice * $paidRenewalYears, 2);
            }

            $renewalOptions[$key] = [
                'expiryDate' => $expiryDateOption[1],
                'amount' => $amount
            ];
        }

        return $renewalOptions;
    }
}
