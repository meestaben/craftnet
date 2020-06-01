<?php

namespace craftnet\controllers\api\v1;

use Craft;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin as Commerce;
use craftnet\controllers\api\BaseApiController;
use Moccalotto\Eu\CountryInfo;
use yii\web\Response;

/**
 * Class CountriesController
 */
class CountriesController extends BaseApiController
{
    const COUNTRY_CACHE_KEY = 'countryListData';
    const COUNTRY_CACHE_DURATION = 60 * 60 * 24 * 7;

    protected $checkCraftHeaders = false;

    /**
     * Handles /v1/countries requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $countryList = $this->_getCountryList();

        return $this->asJson([
            'countries' => $countryList
        ]);
    }

    /**
     * Return a country list populated with state info.
     *
     * @return array
     */
    private function _getCountryList(): array
    {
        $cache = Craft::$app->getCache();

        if ($cache->exists(self::COUNTRY_CACHE_KEY)) {
            return $cache->get(self::COUNTRY_CACHE_KEY);
        }

        $commerce = Commerce::getInstance();

        $countries = $commerce->getCountries()->getAllCountries();
        $states = $commerce->getStates()->getAllStates();

        $sortedStates = [];

        foreach ($states as $state) {
            if (!array_key_exists($state->countryId, $sortedStates)) {
                $sortedStates[$state->countryId] = [];
            }

            $sortedStates[$state->countryId][$state->abbreviation] = $state->name;
        }

        $countryList = [];

        $countryInfo = new CountryInfo();

        foreach ($countries as $country) {
            $countryData = [
                'name' => $country->name,
                'euMember' => $countryInfo->isEuMember($country->iso),
                'stateRequired' => (bool)$country->isStateRequired
            ];

            if (array_key_exists($country->id, $sortedStates)) {
                $countryData['states'] = $sortedStates[$country->id];
            }

            $countryList[$country->iso] = $countryData;
        }

        $cache->set(self::COUNTRY_CACHE_KEY, $countryList, self::COUNTRY_CACHE_DURATION);

        return $countryList;
    }
}
