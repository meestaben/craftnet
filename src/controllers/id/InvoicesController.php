<?php

namespace craftnet\controllers\id;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\Plugin as StripePlugin;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use craftnet\Module;
use Throwable;
use yii\web\Response;

/**
 * Class InvoicesController
 */
class InvoicesController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Get invoices.
     *
     * @return Response
     */
    public function actionGetInvoices(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        $filter = Craft::$app->getRequest()->getParam('filter');
        $limit = Craft::$app->getRequest()->getParam('limit', 10);
        $page = (int)Craft::$app->getRequest()->getParam('page', 1);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy');
        $ascending = Craft::$app->getRequest()->getParam('ascending');

        try {
            $customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($user->id);

            $invoices = [];

            if ($customer) {
                $invoices = Module::getInstance()->getInvoiceManager()->getInvoices($customer, $filter, $limit, $page, $orderBy, $ascending);
            }

            $total = Module::getInstance()->getInvoiceManager()->getTotalInvoices($customer, $filter);

            $last_page = ceil($total / $limit);
            $next_page_url = '?next';
            $prev_page_url = '?prev';
            $from = ($page - 1) * $limit;
            $to = ($page * $limit) - 1;

            return $this->asJson([
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => $last_page,
                'next_page_url' => $next_page_url,
                'prev_page_url' => $prev_page_url,
                'from' => $from,
                'to' => $to,
                'data' => $invoices,
            ]);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Get invoice by its number.
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetInvoiceByNumber(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $number = Craft::$app->getRequest()->getRequiredParam('number');

        try {
            $customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($user->id);

            $invoice = Module::getInstance()->getInvoiceManager()->getInvoiceByNumber($customer, $number);

            return $this->asJson($invoice);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Get invoices.
     *
     * @return Response
     */
    public function actionGetSubscriptionInvoices(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $invoices = StripePlugin::getInstance()->getInvoices()->getUserInvoices($user->id);

        $data = [
            'invoices' => []
        ];

        foreach ($invoices as $invoice) {
            $invoiceData = $invoice->invoiceData;

            $latestStart = 0;

            // Find the latest subscription start time and make it the invoice date.
            foreach ($invoiceData['lines']['data'] as $lineItem) {
                $latestStart = max($latestStart, $lineItem['period']['start']);
            }

            $data['invoices'][] = [
                'date' => DateTimeHelper::toDateTime($latestStart)->format('Y-m-d'),
                'amount' => $invoiceData['total'] / 100,
                'url' => $invoiceData['invoice_pdf'],
            ];
        }

        return $this->asJson($data);
    }
}
