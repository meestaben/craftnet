<?php

namespace craftnet\orders;

use Craft;
use craft\commerce\elements\Order;
use yii\base\BaseObject;

class PdfRenderer extends BaseObject
{
    /**
     * Renders a new PDF for a given order.
     *
     * @param Order $order
     * @return string
     */
    public function render(Order $order): string
    {
        // TCPDF config
        $imagesPath = '../src/orders/receipt/images';
        if (!defined('K_PATH_IMAGES')) {
            define('K_PATH_IMAGES', $imagesPath);
            require_once __DIR__ . '/receipt/tcpdf_config.php';
        }

        // Create a new PDF document
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Craftnet');
        $pdf->SetAuthor('Pixel & Tonic');
        $pdf->SetTitle('Purchase Receipt');
        $pdf->SetSubject('Purchase Receipt');

        $keywords = 'Pixel & Tonic, Receipt, Invoice, Craft CMS, Plugin Store';

        foreach ($order->getLineItems() as $lineItem) {
            if ($purchasable = $lineItem->getPurchasable()) {
                $keywords .= ', ' . $purchasable->getDescription();
            }
        }

        $pdf->SetKeywords($keywords);

        // No header or footer
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set some language-dependent strings
        $pdf->setLanguageArray([
            'a_meta_charset' => 'UTF-8',
            'a_meta_dir' => 'ltr',
            'a_meta_language' => 'en',
            'w_page' => 'page',
        ]);

        // Set default font subsetting mode
        $pdf->setFontSubsetting(true);

        // Add a page
        $pdf->AddPage();

        // Set some content to print
        $view = Craft::$app->getView();
        $oldTemplatesPath = $view->getTemplatesPath();
        $view->setTemplatesPath(__DIR__ . '/receipt/templates');
        $html = $view->renderTemplate('pdf', [
            'order' => $order,
            'imagesPath' => $imagesPath,
        ]);
        $view->setTemplatesPath($oldTemplatesPath);

        // Print text using writeHTMLCell()
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

        return $pdf->Output('doc.pdf', 'S');
    }
}
