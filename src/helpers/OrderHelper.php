<?php

namespace craftnet\helpers;

use craft\commerce\models\LineItem;
use craft\helpers\DateTimeHelper;
use craftnet\base\EditionInterface;
use craftnet\base\RenewalInterface;

abstract class OrderHelper
{
    /**
     * Converts an expiry date string to a DateTime object.
     *
     * @param string $expiryDate
     * @return \DateTime
     */
    public static function expiryStr2Obj(string $expiryDate): \DateTime
    {
        if (preg_match('/^(\d+)y$/', $expiryDate, $matches)) {
            return (new \DateTime('now', new \DateTimeZone('UTC')))
                ->modify("+{$matches[1]} years");
        }

        return DateTimeHelper::toDateTime($expiryDate, false, false);
    }

    /**
     * Converts a DateTime object to an expiry date string
     *
     * @param \DateTime $expiryDate
     * @param string|null $originalStr
     * @return string
     */
    public static function expiryObj2Str(\DateTime $expiryDate, string $originalStr = null): string
    {
        if ($originalStr !== null && static::expiryStr2Obj($originalStr)->getTimestamp() === $expiryDate->getTimestamp()) {
            return $originalStr;
        }

        $expiryDate->setTimezone(new \DateTimeZone('UTC'));
        return $expiryDate->format('Y-m-d');
    }

    /**
     * Calculates the difference in years between two dates.
     *
     * @param \DateTime $d1
     * @param \DateTime $d2
     * @return float
     */
    public static function dateDiffInYears(\DateTime $d1, \DateTime $d2): float
    {
        if ($d1->getTimestamp() === $d2->getTimestamp()) {
            return 0;
        }

        // Make sure $d2 is greater than $d1
        if ($d1 > $d2) {
            [$d1, $d2] = [$d2, $d1];
        }

        $utc = new \DateTimeZone('UTC');
        $d1 = new \DateTime((clone $d1)->setTimezone($utc)->format('Y-m-d'), $utc);
        $d2 = new \DateTime((clone $d2)->setTimezone($utc)->format('Y-m-d'), $utc);
        $diff = $d2->diff($d1);
        $years = $diff->y;

        // Calculate the difference in % into the year each date is
        if ($diff->m !== 0 || $diff->d !== 0) {
            $d1Pct = $d1->format('z') / ($d1->format('L') ? 365 : 364);
            $d2Pct = $d2->format('z') / ($d2->format('L') ? 365 : 364);
            if ($d2Pct >= $d1Pct) {
                $years += $d2Pct - $d1Pct;
            } else {
                $years += 1 - ($d1Pct - $d2Pct);
            }
        }

        return $years;
    }

    /**
     * Populates an edition line item.
     *
     * @param LineItem $lineItem
     * @param EditionInterface $edition
     */
    public static function populateEditionLineItem(LineItem $lineItem, EditionInterface $edition)
    {
        // existing license?
        $options = $lineItem->getOptions();
        $oldExpiryDate = null;

        if (strpos($options['licenseKey'], 'new:') !== 0) {
            $license = $edition->getLicenseByKey($options['licenseKey']);
            if (!$license->getIsExpirable()) {
                return;
            }
            $oldExpiryDate = $license->getExpiryDate();
        }

        // the expiration date must be at least a year from now
        $expiryDate = (new \DateTime())->modify('+1 year');

        // if the line item specifies an expiration date, go with that
        if ($originalExpiryDate = $options['expiryDate'] ?? null) {
            $expiryDate = max($expiryDate, static::expiryStr2Obj($originalExpiryDate));
        }

        // if it's an existing license, make sure the expiration date is at least the current one
        if ($oldExpiryDate) {
            $expiryDate = max($expiryDate, $oldExpiryDate);
        }

        // update the expiration date on the line item
        $expiryDate->setTimezone(new \DateTimeZone('UTC'));
        $options['expiryDate'] = static::expiryObj2Str($expiryDate, $originalExpiryDate);
        $lineItem->setOptions($options);
    }

    /**
     * Populates a renewal line item.
     *
     * @param LineItem $lineItem
     * @param RenewalInterface $renewal
     */
    public static function populateRenewalLineItem(LineItem $lineItem, RenewalInterface $renewal)
    {
        $options = $lineItem->getOptions();
        $license = $renewal->getLicenseByKey($options['licenseKey']);
        /** @var \DateTime $oldExpiryDate */
        $oldExpiryDate = max(new \DateTime('now', new \DateTimeZone('UTC')), $license->getExpiryDate());

        // does the line item specify an expiration date?
        if ($originalExpiryDate = $options['expiryDate'] ?? null) {
            $expiryDate = max($oldExpiryDate, static::expiryStr2Obj($originalExpiryDate));
        } else {
            $expiryDate = (clone $oldExpiryDate)->modify('+1 year');
        }

        // set the price
        $paidRenewalYears = static::dateDiffInYears($oldExpiryDate, $expiryDate);
        if (isset($options['lockedPrice'])) {
            // go with whatever's lower -- the price we told them or the current renewal price
            $renewalPrice = min($options['lockedPrice'], $renewal->getPrice());
        } else {
            $renewalPrice = $renewal->getPrice();
        }
        $lineItem->salePrice = $lineItem->price = $renewalPrice * $paidRenewalYears;

        // update the expiration date on the line item
        $expiryDate->setTimezone(new \DateTimeZone('UTC'));
        $options['expiryDate'] = static::expiryObj2Str($expiryDate, $originalExpiryDate);
        $lineItem->setOptions($options);

        // update the description on the line item
        $lineItem->snapshot['description'] = $renewal->getDescription() . ' - Updates until ' . $expiryDate->format('Y-m-d');
    }
}
