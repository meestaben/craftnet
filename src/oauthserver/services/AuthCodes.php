<?php

namespace craftnet\oauthserver\services;

use Craft;
use craftnet\oauthserver\models\AuthCode;
use craftnet\oauthserver\records\AuthCode as AuthCodeRecord;
use yii\base\Component;

/**
 * Class AuthCodes
 *
 * @property int $id
 * @property int $clientId
 * @property int|null $userId
 * @property string $identifier
 * @property string|null $expiryDate
 * @property string|null $scopes
 */
class AuthCodes extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function getAuthCodes()
    {
        $records = AuthCodeRecord::find()->all();
        $authCodes = [];

        if (count($records) > 0) {
            foreach ($records as $record) {
                $authCodes[] = new AuthCode($record->getAttributes());
            }
        }
        return $authCodes;
    }

    /**
     * @param $id
     *
     * @return AuthCode
     */
    public function getAuthCodeById($id)
    {
        if ($id) {
            $record = AuthCodeRecord::findOne($id);

            if ($record) {
                return new AuthCode($record->getAttributes());
            }
        }
    }

    /**
     * @param $identifier
     *
     * @return AuthCode
     */
    public function getAuthCodeByIdentifier($identifier)
    {
        $record = AuthCodeRecord::findOne(['identifier' => $identifier]);

        if ($record) {
            return new AuthCode($record->getAttributes());
        }
    }

    /**
     * @param AuthCode $model
     *
     * @return bool
     */
    public function saveAuthCode(AuthCode &$model)
    {
        // is new ?
        $isNewAuthCode = !$model->id;

        // populate record
        $record = $this->_getAuthCodeRecordById($model->id);
        $record->identifier = $model->identifier;
        $record->clientId = $model->clientId;
        $record->userId = $model->userId;
        $record->identifier = $model->identifier;
        $record->expiryDate = $model->expiryDate;
        $record->scopes = $model->scopes;

        // save record
        if ($record->save(false)) {
            // populate id
            if ($isNewAuthCode) {
                $model->id = $record->id;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Delete AuthCode By ID
     */
    public function deleteAuthCodeById($id)
    {
        $authCode = $this->getAuthCodeById($id);

        if (!$authCode) {
            return false;
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%oauthserver_auth_codes}}', ['id' => $id])
            ->execute();

        return true;
    }

    /**
     * @return bool
     */
    public function clearAuthCodes()
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%oauthserver_auth_codes}}')
            ->execute();

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param null $id
     *
     * @return AuthCodeRecord|static
     * @throws \Exception
     */
    private function _getAuthCodeRecordById($id = null)
    {
        if ($id) {
            $record = AuthCodeRecord::findOne($id);
            if (!$record) {
                throw new \Exception(Craft::t('app', 'No auth code exists with the ID “{id}”', ['id' => $id]));
            }
        } else {
            $record = new AuthCodeRecord();
        }

        return $record;
    }
}
