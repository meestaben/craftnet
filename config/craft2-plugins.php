<?php
/**
 * Craft 2 Plugin Info
 *
 * This array provides the default information to return about Craft 2 plugins,
 * presented in the "Craft 3 Plugin Availability" report on the Updates page in
 * Craft 2.
 *
 * If a plugin isn't available in the store yet and doesn't have any default
 * info listed here, it will be listed as "Not available yet".
 *
 * If a plugin has been added to a developer's account at id.craftcms.com, then
 * it will be listed as either "Available" or "Coming soon", depending on
 * whether it's enabled in the store yet.
 *
 * To customize the info returned for plugins that aren't available yet, add a
 * new item to this array, using the plugin's Craft 2 handle as the key.
 * Possible values are:
 *
 * - handle:      The plugin's new Craft 3 handle. Only set this if the handle
 *                has changed beyond moving from CamelCase to kebab-case.
 *                It can also be set to `false` if no Craft 3 version exists yet,
 *                and another plugin has taken over its handle.
 * - statusColor: The status color to give the plugin (see below).
 * - status:      The status message to give the plugin. This can include
 *                Markdown formatting.
 *
 * Use one of the following status colors:
 *
 * - green:  Indicates that an upgrade path exists that requires little to no
 *           effort. This should generally be reserved for plugins that have a
 *           direct upgrade available, unless an alternative plugin exists that
 *           won’t involve manual data migration or template changes.
 * - orange: Indicates that an upgrade path exists, but it requires moderate
 *           effort. Use this when an alternative plugin is available, but it
 *           will involve some manual data migration and/or template changes.
 * - red:    Indicates that the plugin has been discontinued and no practical
 *           upgrade path exists yet.
 *
 * Don't specify a status color if Craft 3 compatibility is planned (or even
 * in development), but no upgrade path exists yet.
 */

return [
    'Adminbar' => [
        'handle' => 'admin-bar',
    ],
    'Algolia' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Scout](https://github.com/Rias500/craft-scout) can be used instead.'
    ],
    'AmCommand' => [
        'handle' => 'command-palette',
    ],
    'AmForms' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Freeform](https://github.com/solspace/craft3-freeform) or [Sprout Forms](https://github.com/barrelstrength/craft-sprout-forms) can be used instead.',
    ],
    'AmNav' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Navigation](https://github.com/verbb/navigation) can be used instead.'
    ],
    'AssetPreview' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to built-in asset previewing support in Craft 3.'
    ],
    'AssetRev' => [
        'handle' => 'assetrev',
    ],
    'AssetUsage' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Entry Relations Manager](https://github.com/Frontwise/craft-entryrelationsmanager) can be used instead.'
    ],
    'AuditLog' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Audit](https://github.com/sjelfull/craft-audit) can be used instead.'
    ],
    'Automin' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [SCSS](https://github.com/chasegiunta/craft-scss) can be used instead.'
    ],
    'BackupPro' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Enupal Backup](https://github.com/enupal/backup) can be used instead.'
    ],
    'Brief' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Notifications](https://github.com/Rias500/craft-notifications) or [Notification](https://github.com/fatfishdigital/Craft3-Notification) can be used instead.'
    ],
    'BusinessLogic' => [
        'statusColor' => 'orange',
        'status' => 'Must be updated manually. Use [pluginfactory.io](https://pluginfactory.io/) to generate a Craft 3 plugin scaffolding.'
    ],
    'ButtonBox' => [
        'handle' => 'buttonbox'
    ],
    'CacheBuster' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Asset Rev](https://github.com/clubstudioltd/craft-asset-rev) or [Assets Autoversioning](https://github.com/codemonauts/craft-asset-autoversioning) can be used instead.'
    ],
    'Calendars' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Calendar by Solspace](https://solspace.com/craft/calendar/) can be used instead.'
    ],
    'Charge' => [
        'status' => 'Currently in development.'
    ],
    'CloudfrontInvalidation' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to automatic CloudFront invalidation functionality in the [AWS S3](https://github.com/craftcms/aws-s3) plugin.'
    ],
    'CodeBlock' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Simple Text](https://github.com/craftcms/simple-text) can be used instead.'
    ],
    'ColdCache' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Cache Flag](https://github.com/mmikkel/CacheFlag-Craft3) can be used instead.'
    ],
    'ColorMixer' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Craft Color Mixer](https://github.com/whoisjuan/craft-color-mixer) can be used instead.'
    ],
    'ColorSwatches' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Colour Swatches](https://github.com/rias500/craft-colour-swatches) can be used instead.'
    ],
    'CommerceFriendlyOrderNumbers' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to custom order reference formats in Commerce 2.'
    ],
    'CommerceRegisterOnCheckout' => [
        'status' => 'Not available, but similar functionality is coming to Commerce 2.'
    ],
    'Compressor' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Minify](https://github.com/nystudio107/craft-minify) can be used instead.'
    ],
    'Coordinates' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Smart Map](https://www.doublesecretagency.com/plugins/smart-map) can be used instead.'
    ],
    'CpFieldLinks' => [
        'handle' => 'cp-field-inspect'
    ],
    'CraftCookieConsent' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Cookie Consent Banner](https://github.com/a-digital/cookie-consent-banner) can be used instead.'
    ],
    'Craftnav' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Navigation](https://github.com/verbb/navigation) can be used instead.'
    ],
    'DeleteAllEntryVersions' => [
        'handle' => 'delete-entry-versions'
    ],
    'Detect' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Mobile Detect](https://github.com/sjelfull/craft3-mobiledetect) can be used instead.'
    ],
    'Dump' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Enupal Backup](https://github.com/enupal/backup) can be used instead.'
    ],
    'DuplicateUserDashboard' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Default Dashboard](https://github.com/verbb/default-dashboard) can be used instead.'
    ],
    'EmailObfuscate' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Obfuscator](https://github.com/miranj/craft-obfuscator) or [Craft Email Obfuscator](https://github.com/luke-nehemedia/craft-emailobfuscator) can be used instead.'
    ],
    'EmbeddedAssets' => [
        'handle' => 'embeddedassets',
    ],
    'Embedder' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Video Embedder](https://github.com/mikestecker/craft-videoembedder) or [Videos](https://github.com/dukt/videos) can be used instead.'
    ],
    'EntriesSubset' => [
        'handle' => 'entriessubset'
    ],
    'Export' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Beam](https://github.com/sjelfull/craft3-beam) or [Export CSV](https://github.com/kffein/Craft-export-Csv) can be used instead,'
    ],
    'FeedmeHelper' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed now that the [Feed Me](https://github.com/verbb/feed-me) plugin natively supports CSV.'
    ],
    'FieldGuide' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Field Manager](https://github.com/verbb/field-manager) can be used instead.'
    ],
    'FocusPoint' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to Craft 3’s built-in focal point support. (See Dan Hoerr’s [migration guide](https://github.com/ad-dc/focalpoint_migration).)'
    ],
    'FocalPointField' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to Craft 3’s built-in focal point support.'
    ],
    'FormBuilder2' => [
        'handle' => 'form-builder',
    ],
    'Formerly' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Freeform](https://github.com/solspace/craft3-freeform) or [Sprout Forms](https://github.com/barrelstrength/craft-sprout-forms) can be used instead.',
    ],
    'FruitAviaryImageEditor' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but Craft 3 offers some [image editing features](https://docs.craftcms.com/v3/assets.html#image-editor).'
    ],
    'FruitIcons' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Iconpicker](https://github.com/Dolphiq/craft3-iconpicker) can be used instead.'
    ],
    'FruitLinkIt' => [
        'handle' => 'linkit',
    ],
    'GoogleMaps' => [
        'status' => 'orange',
        'status' => 'Discontinued, but [SimpleMap](https://github.com/ethercreative/simplemap) or [Smart Map](https://www.doublesecretagency.com/plugins/smart-map) can be used instead.'
    ],
    'Geo' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [GeoMate](https://github.com/vaersaagod/geomate) or [Country Detect](https://github.com/serieseight/country-detect) can be used instead.'
    ],
    'GeoAddress' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Smart Map](https://www.doublesecretagency.com/plugins/smart-map) can be used instead.'
    ],
    'GulpRev' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Craft Asset Rev](https://github.com/clubstudioltd/craft-asset-rev) can be used instead.'
    ],
    'Hacksaw' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Wordsmith](https://wordsmith.docs.topshelfcraft.com/guide/truncation.html#hacksaw) or [Typogrify](https://github.com/nystudio107/craft3-typogrify) can be used instead.'
    ],
    'Help' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Guide](https://github.com/wbrowar/craft-3-guide) can be used instead.'
    ],
    'HtmlAttributes' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to Craft 3’s `attr()` Twig function.'
    ],
    'Htmlcache' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [HTML Cache](https://github.com/boldenamsterdam/htmlcache) can be used instead.'
    ],
    'Hue' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to [Color field improvements](https://github.com/craftcms/cms/issues/2059) in Craft 3.'
    ],
    'Import' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Feed Me](https://github.com/verbb/feed-me) or [Sprout Import](https://github.com/barrelstrength/craft-sprout-import) can be used instead.'
    ],
    'Inflect' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Typogrify](https://github.com/nystudio107/craft3-typogrify) or [Wordsmith](https://wordsmith.docs.topshelfcraft.com/guide/inflection.html) can be used instead.'
    ],
    'Inflector' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Typogrify](https://github.com/nystudio107/craft3-typogrify) or [Wordsmith](https://wordsmith.docs.topshelfcraft.com/guide/inflection.html) can be used instead.'
    ],
    'InstagramFeed' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Pic Puller](https://github.com/jmx2inc/picpuller-for-craft3) can be used instead.'
    ],
    'Introvert' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Entry Relations Manager](https://github.com/Frontwise/craft-entryrelationsmanager) can be used instead.'
    ],
    'jSocial' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Simple Sharing](https://github.com/wrav/SimpleSharing) can be used instead.'
    ],
    'LanguageLink' => [
        'handle' => 'site-switcher'
    ],
    'Like' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Upvote](https://github.com/doublesecretagency/craft-upvote) can be used instead.'
    ],
    'LinkVault' => [
        'handle' => 'linkvault'
    ],
    'ListingSection' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Craft Dynamic Fields](https://github.com/lewisjenkins/craft-dynamic-fields) can be used instead.'
    ],
    'Lj_cookies' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Cookies](https://github.com/nystudio107/craft-cookies) can be used instead.'
    ],
    'Lj_DynamicFields' => [
        'handle' => 'craft-dynamic-fields'
    ],
    'Maps' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [SimpleMap](https://github.com/ethercreative/simplemap) or [Smart Map](https://www.doublesecretagency.com/plugins/smart-map) can be used instead.'
    ],
    'Menus' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Navigation](https://github.com/verbb/navigation) can be used instead.'
    ],
    'MigrationManager' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to Project Config support [coming in Craft 3.1](https://craftcms.com/blog/craft-3-1-dev-preview-is-here).'
    ],
    'Minimee' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Minify](https://github.com/nystudio107/craft-minify) can be used instead.'
    ],
    'MnBreakAndContinue' => [
        'handle' => 'twig-perversion'
    ],
    'MNBreakAndContinue' => [
        'handle' => 'twig-perversion'
    ],
    'MnEager' => [
        'handle' => 'agnostic-fetch'
    ],
    'MnSnitch' => [
        'handle' => 'snitch'
    ],
    'MnTwigPerversion' => [
        'handle' => 'twig-perversion'
    ],
    'Moltin' => [
        'status' => 'Discontinued.'
    ],
    'MultiAdd' => [
        'statusColor' => 'orange',
        'status' => 'Not needed anymore thanks to built-in support for multi-add-to-cart in Commerce 2.'
    ],
    'Navee' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Navigation](https://github.com/verbb/navigation) can be used instead.'
    ],
    'NoCache' => [
        'handle' => 'nocache',
    ],
    'NpEditMultipleElements' => [
        'handle' => 'sequential-edit',
    ],
    'ObsoleteRedirect' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Retour](https://github.com/nystudio107/craft-retour) can be used instead.'
    ],
    'Oauth' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to Craft 3’s inclusion of an OAuth 2 client library.'
    ],
    'OneDashboard' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Default Dashboard](https://github.com/verbb/default-dashboard) can be used instead.'
    ],
    'Pdfthumb' => [
        'handle' => false,
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [PDF Transform](https://github.com/bymayo/craft-pdf-transform) or [PDFThumb](https://github.com/jmoont/pdfthumb) can be used instead.'
    ],
    'PhpTweak' => [
        'status' => 'Discontinued.'
    ],
    'PimpMyMatrix' => [
        'handle' => 'spoon',
    ],
    'Postmaster' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Notifications](https://github.com/Rias500/craft-notifications) or [Notification](https://github.com/fatfishdigital/Craft3-Notification) can be used instead.'
    ],
    'Printmaker' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Enupal Snapshot](https://github.com/enupal/snapshot) can be used instead.'
    ],
    'Prune' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to [advanced element query features](https://docs.craftcms.com/v3/dev/element-queries/#advanced-element-queries) in Craft 3.'
    ],
    'Reasons' => [
        'status' => 'Discontinued due to conditional field support [coming](https://github.com/craftcms/cms/issues/805#issuecomment-408128891) in Craft 4.'
    ],
    'RedactorColors' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to changes in the new [Redactor](https://github.com/craftcms/redactor) plugin.'
    ],
    'RedactorExtras' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to changes in the new [Redactor](https://github.com/craftcms/redactor) plugin.'
    ],
    'redactorExtras' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to changes in the new [Redactor](https://github.com/craftcms/redactor) plugin.'
    ],
    'RedactorInlineStyles' => [
        'handle' => 'redactor-custom-styles'
    ],
    'RedactorI' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Redactor](https://github.com/craftcms/redactor) can be used instead.'
    ],
    'RedactorImagePosition' => [
        'statusColor' => 'orange',
        'status' => 'No longer needed thanks to built-in [image positioning support](https://imperavi.com/redactor/examples/images-and-files/image-resizing-and-positioning/).'
    ],
    'RedirectManager' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Retour](https://github.com/nystudio107/craft-retour) can be used instead.'
    ],
    'Relabel' => [
        'handle' => 'fieldlabels',
    ],
    'Reports' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Sprout Reports](https://github.com/barrelstrength/craft-sprout-reports) can be used instead.'
    ],
    'Reroute' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Retour](https://github.com/nystudio107/craft-retour) can be used instead.'
    ],
    'ReverseRelations' => [
        'handle' => 'reverserelations',
    ],
    'RetconHtml' => [
        'handle' => 'retcon'
    ],
    'Scraper' => [
        'status' => 'Currently in development.'
    ],
    'SearchPlus' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Scout](https://github.com/Rias500/craft-scout) can be used instead.'
    ],
    'Shortlist' => [
        'status' => 'Currently in development.'
    ],
    'SmartyPants' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Typogrify](https://github.com/nystudio107/craft3-typogrify) or [Wordsmith](https://wordsmith.docs.topshelfcraft.com/guide/typography.html#smartypants) can be used instead.'
    ],
    'SimpleMap' => [
        'handle' => 'simplemap',
    ],
    'SimpleSitemap' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [XML Sitemap](https://github.com/Dolphiq/craft3-plugin-sitemap) or [SEOmatic](https://github.com/nystudio107/craft-seomatic) or [Sprout SEO](https://github.com/barrelstrength/craft-sprout-seo) can be used instead.',
    ],
    'Sitemap' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [XML Sitemap](https://github.com/Dolphiq/craft3-plugin-sitemap) or [SEOmatic](https://github.com/nystudio107/craft-seomatic) or [Sprout SEO](https://github.com/barrelstrength/craft-sprout-seo) can be used instead.',
    ],
    'Slugify' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Craft Slugify](https://github.com/guilty-as/craft-slugify) can be used instead.'
    ],
    'SocialPoster' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Simple Sharing](https://github.com/wrav/SimpleSharing) can be used instead.'
    ],
    'SpamGuard' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [reCAPTCHA](https://github.com/matt-west/craft-recaptcha) can be used instead.'
    ],
    'Spectrum' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Colorit](https://github.com/fruitstudios/craft-colorit) can be used instead.'
    ],
    'SproutInvisibleCaptcha' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued due to feature being rolled into [Sprout Forms](https://github.com/barrelstrength/craft-sprout-forms).'
    ],
    'SquareBitMaps' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Smart Map](https://www.doublesecretagency.com/plugins/smart-map) can be used instead.'
    ],
    'SuperSort' => [
        'handle' => 'supersort',
    ],
    'SvgIcons' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Icon Picker](https://github.com/verbb/icon-picker) or [Iconpicker](https://github.com/Dolphiq/craft3-iconpicker) can be used instead.'
    ],
    'TableMaker' => [
        'handle' => 'tablemaker'
    ],
    'TaskManager' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Queue Manager](https://github.com/lukeyouell/craft-queue-manager) can be used instead.'
    ],
    'Territories' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Country Select](https://github.com/superbigco/craft-countryredirect) can be used instead.'
    ],
    'TheArchitect' => [
        'handle' => 'architect',
    ],
    'TinyImage' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [ImageOptimize](https://github.com/nystudio107/craft-imageoptimize) can be used instead.'
    ],
    'Trimmer' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Typogrify](https://github.com/nystudio107/craft3-typogrify) or [Wordsmith](https://github.com/TopShelfCraft/Wordsmith) can be used instead.'
    ],
    'Truncate' => [
        'statusColor' => 'orange',
        'status' => 'Discontinued, but [Typogrify](https://github.com/nystudio107/craft3-typogrify) or [Wordsmith](https://github.com/TopShelfCraft/Wordsmith) can be used instead.'
    ],
    'UserManual' => [
        'handle' => 'usermanual'
    ],
    'Venti' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Calendar](https://solspace.com/craft/calendar/) can be used instead.'
    ],
    'VideoEmbed' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Video Embedder](https://github.com/mikestecker/craft-videoembedder) or [Videos](https://github.com/dukt/videos) can be used instead.'
    ],
    'VideoEmbedUtility' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Video Embedder](https://github.com/mikestecker/craft-videoembedder) or [Videos](https://github.com/dukt/videos) can be used instead.'
    ],
    'VzAddress' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Easy Address Field](https://github.com/studioespresso/craft3-easyaddressfield), [NSM Fields](https://github.com/newism/craft3-fields), or [Sprout Fields](https://github.com/barrelstrength/craft-sprout-fields) can be used instead.'
    ],
    'VzUrl' => [
        'handle' => 'vzurl'
    ],
    'Widont' => [
        'statusColor' => 'orange',
        'status' => 'Not available yet, but [Wordsmith](https://wordsmith.docs.topshelfcraft.com/guide/typography.html#widont) can be used instead.'
    ],
];
