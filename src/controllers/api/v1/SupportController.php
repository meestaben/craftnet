<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\i18n\Locale;
use craft\web\UploadedFile;
use craftnet\cms\CmsLicense;
use craftnet\controllers\api\BaseApiController;
use craftnet\helpers\Zendesk;
use yii\helpers\Markdown;
use yii\web\Response;

/**
 * Class SupportController
 */
class SupportController extends BaseApiController
{
    /**
     * Creates a new support request
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCreate(): Response
    {
        $request = Craft::$app->getRequest();
        $requestHeaders = $request->getHeaders();
        $body = $request->getRequiredBodyParam('message');

        $info = [];
        /** @var CmsLicense $cmsLicense */
        $cmsLicense = reset($this->cmsLicenses) ?: null;
        $formatter = Craft::$app->getFormatter();

        if ($this->cmsEdition !== null || $this->cmsVersion !== null) {
            $craftInfo = 'Craft' .
                ($this->cmsEdition !== null ? ' ' . ucfirst($this->cmsEdition) : '') .
                ($this->cmsVersion !== null ? ' ' . $this->cmsVersion : '');

            if ($cmsLicense && $cmsLicense->editionHandle !== $this->cmsEdition) {
                $craftInfo .= ' (trial)';
            }

            $info[] = $craftInfo;
        }

        if ($cmsLicense) {
            $licenseInfo = [
                '`' . $cmsLicense->getShortKey() . '` (' . ucfirst($cmsLicense->editionHandle) . ')',
                'from ' . $formatter->asDate($cmsLicense->dateCreated, Locale::LENGTH_SHORT),
            ];
            if ($cmsLicense->expirable && $cmsLicense->expiresOn) {
                $licenseInfo[] .= ($cmsLicense->expired ? 'expired on' : 'expires on') .
                    ' ' . $formatter->asDate($cmsLicense->expiresOn, Locale::LENGTH_SHORT);
            }
            if ($cmsLicense->domain) {
                $licenseInfo[] = 'for ' . $cmsLicense->domain;
            }
            $info[] = 'License: ' . implode(', ', $licenseInfo);
        }

        if (!empty($this->pluginVersions)) {
            $pluginInfos = [];
            foreach ($this->pluginVersions as $pluginHandle => $pluginVersion) {
                if ($plugin = $this->plugins[$pluginHandle] ?? null) {
                    $pluginInfo = "[{$plugin->name}](https://plugins.craftcms.com/{$plugin->handle})";
                } else {
                    $pluginInfo = $pluginHandle;
                }
                if (($edition = $this->pluginEditions[$pluginHandle] ?? null) && $edition !== 'standard') {
                    $pluginInfo .= ' ' . ucfirst($edition);
                }
                $pluginInfo .= ' ' . $pluginVersion;
                $pluginInfos[] = $pluginInfo;
            }
            $info[] = 'Plugins: ' . implode(', ', $pluginInfos);
        }

        if (($host = $requestHeaders->get('X-Craft-Host')) !== null) {
            $info[] = 'Host: ' . $host;
        }

        if (!empty($info)) {
            $body .= "\n\n---\n\n" . implode("  \n", $info);
        }

        $client = Zendesk::client();
        $uploadTokens = [];

        $attachments = UploadedFile::getInstancesByName('attachments');
        if (empty($attachments) && $attachment = UploadedFile::getInstanceByName('attachment')) {
            $attachments = [$attachment];
        }

        if (!empty($attachments)) {
            foreach ($attachments as $i => $attachment) {
                if (!empty($attachment->tempName)) {
                    $response = $client->attachments()->upload([
                        'file' => $attachment->tempName,
                        'type' => $attachment->getMimeType(),
                        'name' => $attachment->name,
                    ]);
                    $uploadTokens[] = $response->upload->token;
                }
            }
        }

        Zendesk::client()->tickets()->create([
            'requester' => [
                'name' => $request->getRequiredBodyParam('name'),
                'email' => $request->getRequiredBodyParam('email'),
            ],
            'subject' => getenv('FRONT_SUBJECT'),
            'comment' => [
                'body' => $body,
                'html_body' => Markdown::process($body, 'gfm'),
                'uploads' => $uploadTokens,
            ],
            'type' => 'question',
            'tags' => [getenv('FRONT_TAG')],
        ]);

        return $this->asJson([
            'sent' => true,
        ]);
    }
}
