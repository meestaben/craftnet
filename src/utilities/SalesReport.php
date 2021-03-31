<?php

namespace craftnet\utilities;

use Craft;
use craft\base\Utility;
use craft\commerce\elements\Order;
use craft\db\Query;

class SalesReport extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Sales Report');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'sales-report';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/shopping-cart.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $request = Craft::$app->getRequest();

        // See if it's in a format we recognize.
        $dateStart = $request->getParam('dateStart');
        $formattedStartDate = \DateTime::createFromFormat('m-d-Y', $dateStart, new \DateTimeZone('UTC'));

        if (!$formattedStartDate) {
            $formattedStartDate = \DateTime::createFromFormat('m/d/Y', $dateStart, new \DateTimeZone('UTC'));
        }

        if (!$dateStart || !$formattedStartDate) {
            // default to current month and year if it's not there or in an incorrect format.
            $dateStart = date('Y-m-01 00:00:00');
        } else {
            $dateStart = $formattedStartDate->format('Y-m-d 00:00:00');
        }

        // See if it's in a format we recognize.
        $dateEnd = $request->getParam('dateEnd');
        $formattedEndDate = \DateTime::createFromFormat('m-d-Y', $dateEnd, new \DateTimeZone('UTC'));

        if (!$formattedEndDate) {
            $formattedEndDate = \DateTime::createFromFormat('m/d/Y', $dateEnd, new \DateTimeZone('UTC'));
        }

        if (!$dateEnd || !$formattedEndDate) {
            // default to current month and year if it's not there or in an incorrect format.
            $dateEnd = date('Y-m-t 23:59:59');
        } else {
            $dateEnd = $formattedEndDate->format('Y-m-d 23:59:59');
        }

        $gatewayPurchasables = [
            'COMMERCE-WORLDPAY-STANDARD',
            'COMMERCE-EWAY-STANDARD',
            'COMMERCE-MOLLIE-STANDARD',
            'COMMERCE-MULTISAFEPAY-STANDARD',
            'COMMERCE-SAGEPAY-STANDARD',
        ];

        $commercePurchasable = [
            'COMMERCE-STANDARD',
            'COMMERCE-PRO',
            'COMMERCE-LITE',
        ];

        $craftPurchasables = [
            'CRAFT-PRO',
            'CRAFT-CLIENT',
        ];

        $allPurchasables = array_merge($craftPurchasables, $commercePurchasable);
        $allPurchasables = array_merge($allPurchasables, $gatewayPurchasables);

        $orders = Order::find()
            ->datePaid(['and', '>=' . $dateStart, '<=' . $dateEnd])
            ->orderBy('datePaid')
            ->all();

        $devPayments = [];

        foreach ($orders as $order) {
            $payments = (new Query())
                ->select(['*'])
                ->from(['craftnet_developerledger dl'])
                ->where(['ilike', 'note', $order['number']])
                ->orderBy('dateCreated')
                ->all();

            foreach ($payments as $payment) {
                $devPayments[$order->id][] = [
                    'fee' => $payment['fee'],
                    'debit' => $payment['debit'],
                    'credit' => $payment['credit'],
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate('craftnet/sales-report/_content', [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'orders' => $orders,
            'devPayments' => $devPayments,
            'allPurchasables' => $allPurchasables,
            'craftPurchasables' => $craftPurchasables,
            'commercePurchasables' => $commercePurchasable,
            'gatewayPurchasables' => $gatewayPurchasables,
        ]);
    }
}
