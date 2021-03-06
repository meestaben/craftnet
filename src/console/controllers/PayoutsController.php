<?php

namespace craftnet\console\controllers;

use craftnet\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manages Composer packages.
 *
 * @property Module $module
 */
class PayoutsController extends Controller
{
    /**
     * Sends payouts to any developers we owe $ to, who have provided their PayPal email.
     *
     * @return int
     */
    public function actionSend(): int
    {
        $payout = $this->module->getPayoutManager()->sendPayout();

        if (!$payout) {
            $this->stdout("No accounts have outstanding balances owed.\n", Console::FG_GREEN);
        } else {
            $totalItems = $payout->getItems()->count();
            $this->stdout("Initiated payouts for $totalItems developers.\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Checks on the status of in-progress payouts.
     *
     * @return int
     */
    public function actionUpdate(): int
    {
        $payouts = $this->module->getPayoutManager()->updateInProgressPayouts();

        if (empty($payouts)) {
            $this->stdout("There aren’t any in-progress payouts.\n", Console::FG_GREEN);
        } else {
            $totalPayouts = count($payouts);
            $this->stdout("Updated $totalPayouts payouts.\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
