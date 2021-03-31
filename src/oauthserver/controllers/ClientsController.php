<?php

namespace craftnet\oauthserver\controllers;

use Craft;
use craft\web\Controller;
use craftnet\oauthserver\models\Client;
use craftnet\oauthserver\Module as OauthServer;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class ClientsController
 */
class ClientsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex()
    {
        $clients = OauthServer::getInstance()->getClients()->getClients();

        return $this->renderTemplate('oauth-server/clients', [
            'clients' => $clients,
        ]);
    }

    /**
     * @param int|null $clientId
     * @param Client|null $client
     *
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $clientId = null, Client $client = null)
    {
        $variables = [
            'clientId' => $clientId,
            'brandNewClient' => false,
        ];

        if ($clientId !== null) {
            if ($client === null) {
                $client = OauthServer::getInstance()->getClients()->getClientById($clientId);

                if (!$client) {
                    throw new NotFoundHttpException('Client not found');
                }
            }

            $variables['title'] = $client->name;
        } else {
            if ($client === null) {
                $client = new Client();
                $variables['brandNewClient'] = true;
            }

            $variables['title'] = Craft::t('app', 'Create a new client');
        }

        $variables['client'] = $client;

        return $this->renderTemplate('oauth-server/clients/_edit', $variables);
    }

    /**
     * @return null|Response
     */
    public function actionSaveClient()
    {
        $this->requirePostRequest();

        $client = new Client;
        $client->id = Craft::$app->request->getBodyParam('id');
        $client->name = Craft::$app->request->getBodyParam('name');
        $client->identifier = Craft::$app->request->getBodyParam('identifier');
        $client->secret = Craft::$app->request->getBodyParam('secret');
        $client->redirectUri = Craft::$app->request->getBodyParam('redirectUri');
        $client->redirectUriLocked = (Craft::$app->request->getBodyParam('redirectUriLocked') ? Craft::$app->request->getBodyParam('redirectUriLocked') : false);


        // Save it
        if (!OauthServer::getInstance()->getClients()->saveClient($client)) {
            Craft::$app->getSession()->setError(Craft::t('app', "Couldn't save client."));

            // Send the site back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'client' => $client,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Client saved.'));

        return $this->redirectToPostedUrl($client);
    }

    /**
     * @return Response
     */
    public function actionDeleteClient(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $clientId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        OauthServer::getInstance()->getClients()->deleteClientById($clientId);

        return $this->asJson(['success' => true]);
    }
}
