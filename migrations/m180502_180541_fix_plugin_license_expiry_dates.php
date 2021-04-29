<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craftnet\db\Table;

/**
 * m180502_180541_fix_plugin_license_expiry_dates migration.
 */
class m180502_180541_fix_plugin_license_expiry_dates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $requests = (new Query())
            ->select(['id', 'body', 'timestamp'])
            ->from(['apilog.requests'])
            ->where(['like', 'body', '"expiresOn"'])
            ->andWhere(['>=', 'timestamp', Db::prepareDateForDb(new \DateTime('2018-04-17'))])
            ->all();

        foreach ($requests as $request) {
            // find the license created by this request
            $body = Json::decode($request['body']);
            $licenseId = (new Query())
                ->select(['id'])
                ->from(Table::PLUGINLICENSES)
                ->where([
                    'pluginHandle' => $body['plugin'],
                    'email' => $body['email'],
                ])
                ->andWhere(['<=', 'dateCreated', $request['timestamp']])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->scalar();

            if ($licenseId === false) {
                echo "    > could not find a license for request {$request['id']}\n";
                return false;
            }

            // update its expiresOn value
            $expiresOn = DateTimeHelper::toDateTime($body['expiresOn']);
            $expiresOnSql = Db::prepareDateForDb($expiresOn);
            echo "    > setting expiry date for license {$licenseId} to {$expiresOnSql}\n";
            $this->update(Table::PLUGINLICENSES, [
                'expiresOn' => $expiresOnSql
            ], [
                'id' => $licenseId,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180502_180541_fix_plugin_license_expiry_dates cannot be reverted.\n";
        return false;
    }
}
