<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\web\UploadedFile;
use craftnet\cms\CmsLicense;
use craftnet\cms\CmsLicenseManager;
use craftnet\controllers\api\BaseApiController;
use craftnet\events\ZendeskEvent;
use craftnet\helpers\Zendesk;
use yii\helpers\Markdown;
use yii\web\Response;

/**
 * Class SupportController
 */
class SupportController extends BaseApiController
{
    /**
     * @event ZendeskEvent
     */
    const EVENT_CREATE_TICKET = 'createTicket';

    /**
     * Creates a new support request
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCreate(): Response
    {
        Craft::error('Support - Made it to create.');
        $request = Craft::$app->getRequest();
        $requestHeaders = $request->getHeaders();
        $body = $request->getRequiredBodyParam('message');
        /** @var CmsLicense $cmsLicense */
        $cmsLicense = reset($this->cmsLicenses) ?: null;
        $customFields = [];

        if ($this->cmsVersion) {
            $customFields[] = [
                'id' => getenv('ZENDESK_FIELD_CRAFT_VERSION'),
                'value' => $this->cmsVersion
            ];
        }

        if (
            $this->cmsEdition &&
            in_array($this->cmsEdition, [CmsLicenseManager::EDITION_SOLO, CmsLicenseManager::EDITION_PRO], true)
        ) {
            $trial = $cmsLicense && $cmsLicense->editionHandle !== $this->cmsEdition;
            $customFields[] = [
                'id' => getenv('ZENDESK_FIELD_CRAFT_EDITION'),
                'value' => "edition_{$this->cmsEdition}" . ($trial ? '_trial' : '')
            ];
        }

        if ($cmsLicense) {
            $customFields[] = [
                'id' => getenv('ZENDESK_FIELD_CRAFT_LICENSE'),
                'value' => $cmsLicense->key
            ];
        }

        if (!empty($this->pluginVersions)) {
            $pluginInfos = [];
            foreach ($this->pluginVersions as $pluginHandle => $pluginVersion) {
                if ($plugin = $this->plugins[$pluginHandle] ?? null) {
                    $pluginInfo = $plugin->name;
                } else {
                    $pluginInfo = $pluginHandle;
                }
                if (($edition = $this->pluginEditions[$pluginHandle] ?? null) && $edition !== 'standard') {
                    $pluginInfo .= ' ' . ucfirst($edition);
                }
                $pluginInfo .= ' ' . $pluginVersion;
                $pluginInfos[] = $pluginInfo;
            }
            $customFields[] = [
                'id' => getenv('ZENDESK_FIELD_PLUGINS'),
                'value' => implode("\n", $pluginInfos)
            ];
        }

        if (($host = $requestHeaders->get('X-Craft-Host')) !== null) {
            $customFields[] = [
                'id' => getenv('ZENDESK_FIELD_HOST'),
                'value' => $host
            ];
        }
        Craft::error('Support - Creating ZD client.');
        $client = Zendesk::client();
        $uploadTokens = [];

        $attachments = UploadedFile::getInstancesByName('attachments');
        Craft::error('Support - count($attachments) 1 = '.count($attachments));
        if (empty($attachments) && $attachment = UploadedFile::getInstanceByName('attachment')) {
            $attachments = [$attachment];
            Craft::error('Support - count($attachments) 2 = '.count($attachments));
        }

        if (!empty($attachments)) {
            Craft::error('Support - Found '.count($attachments).' attachments to send to ZenDesk.');
            foreach ($attachments as $i => $attachment) {
                Craft::error('Support - Attachment Name: '.$attachment->name);
                if (!empty($attachment->tempName)) {
                    Craft::error('Support - Attachment Temp Name: '.$attachment->tempName);
                    $response = $client->attachments()->upload([
                        'file' => $attachment->tempName,
                        'type' => $attachment->getMimeType(),
                        'name' => $attachment->name,
                    ]);
                    Craft::error('Support - Attachment Upload Token: '.$response->upload->token);
                    $uploadTokens[] = $response->upload->token;
                }
            }
        }

        $email = mb_strtolower($request->getRequiredBodyParam('email'));
        $plan = Zendesk::plan($email);
        $tags = [getenv('ZENDESK_TAG'), $plan];

        $response = Zendesk::client()->tickets()->create([
            'requester' => [
                'name' => $request->getRequiredBodyParam('name'),
                'email' => $email,
            ],
            'subject' => getenv('ZENDESK_SUBJECT'),
            'comment' => [
                'body' => $body,
                'html_body' => Markdown::process($body, 'gfm'),
                'uploads' => $uploadTokens,
            ],
            'tags' => $tags,
            'custom_fields' => $customFields,
        ]);

        Craft::error('Support - Response: '.Craft::dd($response));

        $this->trigger(self::EVENT_CREATE_TICKET, new ZendeskEvent([
            'ticketId' => $response->ticket->id,
            'email' => $email,
            'tags' => $tags,
            'plan' => $plan,
        ]));

        return $this->asJson([
            'sent' => true,
        ]);
    }
}
