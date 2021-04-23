<?php

namespace craftnet;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\PdfEvent;
use craft\commerce\models\Discount;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\Pdfs;
use craft\commerce\services\Purchasables;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\elements\Asset;
use craft\elements\db\UserQuery;
use craft\elements\User;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DeleteElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\UserEvent;
use craft\fieldlayoutelements\StandardTextField;
use craft\helpers\App;
use craft\models\FieldLayout;
use craft\models\SystemMessage;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\SystemMessages;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\services\Utilities;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use craftnet\behaviors\AssetBehavior;
use craftnet\behaviors\DiscountBehavior;
use craftnet\behaviors\OrderBehavior;
use craftnet\behaviors\UserBehavior;
use craftnet\behaviors\UserQueryBehavior;
use craftnet\cms\CmsEdition;
use craftnet\cms\CmsLicenseManager;
use craftnet\composer\JsonDumper;
use craftnet\composer\PackageManager;
use craftnet\fields\Plugins;
use craftnet\invoices\InvoiceManager;
use craftnet\orders\PdfRenderer;
use craftnet\payouts\PayoutManager;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginLicenseManager;
use craftnet\sales\SaleManager;
use craftnet\services\Oauth;
use craftnet\utilities\PullProduction;
use craftnet\utilities\SalesReport;
use craftnet\utilities\UnavailablePlugins;
use yii\base\Event;

/**
 * @property-read CmsLicenseManager $cmsLicenseManager
 * @property-read InvoiceManager $invoiceManager
 * @property-read JsonDumper $jsonDumper
 * @property-read Oauth $oauth
 * @property-read PackageManager $packageManager
 * @property-read PayoutManager $payoutManager
 * @property-read PluginLicenseManager $pluginLicenseManager
 * @property-read SaleManager $saleManager
 */
class Module extends \yii\base\Module
{
    const MESSAGE_KEY_RECEIPT = 'craftnet_receipt';
    const MESSAGE_KEY_VERIFY = 'verify_email';
    const MESSAGE_KEY_DEVELOPER_SALE = 'developer_sale';
    const MESSAGE_KEY_LICENSE_REMINDER = 'license_reminder';
    const MESSAGE_KEY_LICENSE_NOTIFICATION = 'license_notification';
    const MESSAGE_KEY_LICENSE_TRANSFER = 'license_transfer';
    const MESSAGE_KEY_SECURITY_ALERT = 'security_alert';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        Craft::setAlias('@craftnet', __DIR__);

        $this->_defineCustomBehaviors();
        $this->_registerCustomComponentTypes();
        $this->_registerCustomReceiptMessages();
        $this->_registerGql();

        // claim Craft/plugin licenses after user activation
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ACTIVATE_USER,
            function(UserEvent $event) {
                $this->getCmsLicenseManager()->claimLicenses($event->user);
                $this->getPluginLicenseManager()->claimLicenses($event->user);
            }
        );

        // provide custom order receipt PDF generation
        Event::on(
            Pdfs::class,
            Pdfs::EVENT_BEFORE_RENDER_PDF,
            function(PdfEvent $event) {
                $event->pdf = (new PdfRenderer())->render($event->order);
            }
        );

        // hard-delete plugins
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(DeleteElementEvent $event) {
                if ($event->element instanceof Plugin) {
                    $event->hardDelete = true;
                }
            }
        );

        // request type-specific stuff
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'craftnet\\console\\controllers';
            $this->_initConsoleRequset();
        } else {
            $this->controllerNamespace = 'craftnet\\controllers';

            if ($request->getIsCpRequest()) {
                $this->_initCpRequest();
            } else {
                $this->_initSiteRequest();
            }
        }

        parent::init();
    }

    /**
     * @return CmsLicenseManager
     */
    public function getCmsLicenseManager(): CmsLicenseManager
    {
        return $this->get('cmsLicenseManager');
    }

    /**
     * @return InvoiceManager
     */
    public function getInvoiceManager(): InvoiceManager
    {
        return $this->get('invoiceManager');
    }

    /**
     * @return PluginLicenseManager
     */
    public function getPluginLicenseManager(): PluginLicenseManager
    {
        return $this->get('pluginLicenseManager');
    }

    /**
     * @return PackageManager
     */
    public function getPackageManager(): PackageManager
    {
        return $this->get('packageManager');
    }

    /**
     * @return JsonDumper
     */
    public function getJsonDumper(): JsonDumper
    {
        return $this->get('jsonDumper');
    }

    /**
     * @return Oauth
     */
    public function getOauth(): Oauth
    {
        return $this->get('oauth');
    }

    /**
     * @return SaleManager
     */
    public function getSaleManager(): SaleManager
    {
        return $this->get('saleManager');
    }

    /**
     * @return PayoutManager
     */
    public function getPayoutManager(): PayoutManager
    {
        return $this->get('payoutManager');
    }

    /**
     * Register custom behaviors.
     */
    private function _defineCustomBehaviors(): void
    {
        Event::on(
            Asset::class,
            Asset::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['cn.asset'] = AssetBehavior::class;
            }
        );

        Event::on(
            UserQuery::class,
            UserQuery::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['cn.userQuery'] = UserQueryBehavior::class;
            }
        );

        Event::on(
            User::class,
            User::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['cn.user'] = UserBehavior::class;
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['cn.order'] = OrderBehavior::class;
            }
        );

        Event::on(
            Discount::class,
            Discount::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['cn.discount'] = DiscountBehavior::class;
            }
        );
    }

    /**
     * Register custom fields, utilities, purchasables, and order adjustments.
     */
    private function _registerCustomComponentTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Plugins::class;
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = UnavailablePlugins::class;
                $event->types[] = SalesReport::class;
                $event->types[] = PullProduction::class;
            }
        );

        Event::on(
            Purchasables::class,
            Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CmsEdition::class;
                $event->types[] = PluginEdition::class;
            }
        );

        Event::on(
            OrderAdjustments::class,
            OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = OrderAdjuster::class;
            }
        );
    }

    /**
     * Register custom email receipt messages.
     */
    private function _registerCustomReceiptMessages(): void
    {
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_RECEIPT,
                    'heading' => 'When someone places an order:',
                    'subject' => 'Your receipt from {{ fromName }}',
                    'body' => file_get_contents(__DIR__ . '/emails/receipt.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_VERIFY,
                    'heading' => 'When someone wants to claim licenses by an email address:',
                    'subject' => 'Verify your email',
                    'body' => file_get_contents(__DIR__ . '/emails/verify.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_DEVELOPER_SALE,
                    'heading' => 'When a plugin developer makes a sale:',
                    'subject' => 'Craft Plugin Store Sale',
                    'body' => file_get_contents(__DIR__ . '/emails/developer_sale.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_LICENSE_REMINDER,
                    'heading' => 'When licenses will be expiring/auto-renewing soon:',
                    'subject' => 'Important license info',
                    'body' => file_get_contents(__DIR__ . '/emails/license_reminder.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_LICENSE_NOTIFICATION,
                    'heading' => 'When licenses have expired/auto-renewed:',
                    'subject' => 'Important license info',
                    'body' => file_get_contents(__DIR__ . '/emails/license_notification.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_LICENSE_TRANSFER,
                    'heading' => 'When a license has been transferred to a new plugin/edition:',
                    'subject' => 'Important license info',
                    'body' => file_get_contents(__DIR__ . '/emails/license_transfer.md'),
                ]);

                $event->messages[] = new SystemMessage([
                    'key' => self::MESSAGE_KEY_SECURITY_ALERT,
                    'heading' => 'When a critical update is available:',
                    'subject' => 'Urgent: You must update {{ name }} now',
                    'body' => file_get_contents(__DIR__ . '/emails/security_alert.md'),
                ]);
            }
        );
    }

    /**
     * Register the bits and pieces that add GraphQL support for custom elements.
     */
    private function _registerGql(): void
    {
        // register element types that should appear in the GraphQL schema
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function(RegisterGqlTypesEvent $event) {
                $event->types[] = \craftnet\gql\interfaces\elements\Partner::class;
                $event->types[] = \craftnet\gql\interfaces\elements\Plugin::class;
            }
        );

        // register GraphQL queries
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function(RegisterGqlQueriesEvent $event) {
                $event->queries = array_merge(
                    $event->queries,
                    \craftnet\gql\queries\Partner::getQueries(),
                    \craftnet\gql\queries\Plugin::getQueries()
                );
            }
        );

        // register GraphQL permissions
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS,
            function(RegisterGqlSchemaComponentsEvent $event) {
                $event->queries = array_merge($event->queries, [
                    'Partners' => [
                        'partners:read' => ['label' => 'View Partners']
                    ],
                    'Plugins' => [
                        'plugins:read' => ['label' => 'View Plugins']
                    ],
                ]);
            }
        );
    }

    /**
     * Define custom console request actions.
     */
    private function _initConsoleRequset(): void
    {
        Event::on(
            ResaveController::class,
            ConsoleController::EVENT_DEFINE_ACTIONS,
            function(DefineConsoleActionsEvent $event) {
                $event->actions['plugins'] = [
                    'action' => function(): int {
                        /** @var ResaveController $controller */
                        $controller = Craft::$app->controller;
                        return $controller->saveElements(Plugin::find());
                    },
                    'options' => [],
                    'helpSummary' => 'Re-saves Plugin Store plugins.',
                ];
            }
        );
    }

    /**
     * Define custom control panel nav items, routes, template root, permissions,
     * and a standard (not custom) PayPal Email field for users.
     */
    private function _initCpRequest(): void
    {
        $this->controllerNamespace = 'craftnet\\controllers';

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'cmslicenses',
                    'label' => 'Craft Licenses',
                ];

                $event->navItems[] = [
                    'url' => 'plugins',
                    'label' => 'Plugins',
                    'fontIcon' => 'plugin',
                ];

                $event->navItems[] = [
                    'url' => 'partners',
                    'label' => 'Partners',
                    'icon' => __DIR__ . '/icons/partner.svg',
                ];
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'cmslicenses' => 'craftnet/cms-licenses',
                    'plugins' => ['template' => 'craftnet/plugins/_index'],
                    'plugins/new' => 'craftnet/plugins/edit',
                    'plugins/<pluginId:\d+><slug:(?:-[^\/]*)?>' => 'craftnet/plugins/edit',
                    'partners' => ['template' => 'craftnet/partners/_index'],
                    'partners/new' => 'craftnet/partners/edit',
                    'partners/<partnerId:\d+><slug:(?:-[^\/]*)?>' => 'craftnet/partners/edit',
                    'partners/foo' => 'craftnet/partners/foo',
                    'GET partners/history/<partnerId:\d+>' => 'craftnet/partners/fetch-history',
                    'POST partners/history' => 'craftnet/partners/save-history',
                    'DELETE partners/history/<id:\d+>' => 'craftnet/partners/delete-history',
                ]);
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['craftnet'] = __DIR__ . '/templates';
            }
        );

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Craftcom'] = [
                    'craftnet:managePlugins' => [
                        'label' => 'Manage plugins',
                    ],
                ];
            }
        );

        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_STANDARD_FIELDS,
            function(DefineFieldLayoutFieldsEvent $event) {
                /** @var FieldLayout $fieldLayout */
                $fieldLayout = $event->sender;

                switch ($fieldLayout->type) {
                    case User::class:
                        $event->fields[] = [
                            'class' => StandardTextField::class,
                            'attribute' => 'payPalEmail',
                            'label' => 'PayPal Email',
                            'mandatory' => true,
                        ];
                        break;
                }
            }
        );
    }

    /**
     * Set `Access-Control-Allow-Origin` according to request source.
     */
    private function _initSiteRequest(): void
    {
        $idOrigin = rtrim(App::env('URL_ID'), '/');

        if (Craft::$app->getRequest()->getOrigin() === $idOrigin) {
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Origin', $idOrigin);
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Credentials', 'true');
        } else {
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Origin', '*');
        }

        Craft::$app->getResponse()->getHeaders()->set('X-Frame-Options', 'sameorigin');
    }
}
