<?php

namespace craftnet\partners;


use craft\base\Element;
use craft\base\Model;

class PartnerLocation extends Model
{
    public $id;
    public $partnerId;
    public $title;
    public $addressLine1;
    public $addressLine2;
    public $city;
    public $state;
    public $zip;
    public $country;
    public $phone;
    public $email;
    public $dateCreated;
    public $dateUpdated;
    public $uid;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [
            [
                'title',
                'addressLine1',
                'city',
                'state',
                'zip',
                'country',
                'email',
            ],
            'required',
            'on' => [
                Element::SCENARIO_DEFAULT,
                Element::SCENARIO_LIVE,
                Partner::SCENARIO_LOCATIONS,
            ],
        ];

        $rules[] = ['email', 'email', 'enableIDN' => true];

        return $rules;
    }
}
