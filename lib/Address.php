<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce;

use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\ViewState\AddressState;

/**
 * Class Address
 */
class Address extends AbstractViewComponent
{

    /**
     * @var \PatternSeek\ECommerce\Basket
     */
    protected $parent;

    /**
     * @var AddressState An object containing state elements
     */
    protected $state;

    /**
     * @return array
     */
    public static function getCountriesByISO()
    {
        return [
            'AF' => "Afghanistan",
            'AX' => "Åland Islands",
            'AL' => "Albania",
            'DZ' => "Algeria",
            'AS' => "American Samoa",
            'AD' => "Andorra",
            'AO' => "Angola",
            'AI' => "Anguilla",
            'AQ' => "Antarctica",
            'AG' => "Antigua and Barbuda",
            'AR' => "Argentina",
            'AM' => "Armenia",
            'AW' => "Aruba",
            'AU' => "Australia",
            'AT' => "Austria",
            'AZ' => "Azerbaijan",
            'BS' => "Bahamas",
            'BH' => "Bahrain",
            'BD' => "Bangladesh",
            'BB' => "Barbados",
            'BY' => "Belarus",
            'BE' => "Belgium",
            'BZ' => "Belize",
            'BJ' => "Benin",
            'BM' => "Bermuda",
            'BT' => "Bhutan",
            'BO' => "Bolivia",
            'BQ' => "Bonaire, Sint Eustatius and Saba",
            'BA' => "Bosnia and Herzegovina",
            'BW' => "Botswana",
            'BV' => "Bouvet Island",
            'BR' => "Brazil",
            'IO' => "British Indian Ocean Territory",
            'BN' => "Brunei",
            'BG' => "Bulgaria",
            'BF' => "Burkina Faso",
            'BI' => "Burundi",
            'KH' => "Cambodia",
            'CM' => "Cameroon",
            'CA' => "Canada",
            'CV' => "Cabo Verde",
            'KY' => "Cayman Islands",
            'CF' => "Central African Republic",
            'TD' => "Chad",
            'CL' => "Chile",
            'CN' => "China",
            'CX' => "Christmas Island",
            'CC' => "Cocos (Keeling) Islands",
            'CO' => "Colombia",
            'KM' => "Comoros",
            'CG' => "Congo",
            'CD' => "Congo (Democratic Republic of the)",
            'CK' => "Cook Islands",
            'CR' => "Costa Rica",
            'CI' => "Côte d'Ivoire",
            'HR' => "Croatia",
            'CU' => "Cuba",
            'CW' => "Curaçao",
            'CY' => "Cyprus",
            'CZ' => "Czech Republic",
            'DK' => "Denmark",
            'DJ' => "Djibouti",
            'DM' => "Dominica",
            'DO' => "Dominican Republic",
            'EC' => "Ecuador",
            'EG' => "Egypt",
            'SV' => "El Salvador",
            'GQ' => "Equatorial Guinea",
            'ER' => "Eritrea",
            'EE' => "Estonia",
            'ET' => "Ethiopia",
            'FK' => "Falkland Islands (Malvinas)",
            'FO' => "Faroe Islands",
            'FJ' => "Fiji",
            'FI' => "Finland",
            'FR' => "France",
            'GF' => "French Guiana",
            'PF' => "French Polynesia",
            'TF' => "French Southern Territories",
            'GA' => "Gabon",
            'GM' => "Gambia",
            'GE' => "Georgia",
            'DE' => "Germany",
            'GH' => "Ghana",
            'GI' => "Gibraltar",
            'GR' => "Greece",
            'GL' => "Greenland",
            'GD' => "Grenada",
            'GP' => "Guadeloupe",
            'GU' => "Guam",
            'GT' => "Guatemala",
            'GG' => "Guernsey",
            'GN' => "Guinea",
            'GW' => "Guinea-Bissau",
            'GY' => "Guyana",
            'HT' => "Haiti",
            'HM' => "Heard Island and McDonald Islands",
            'VA' => "Holy See",
            'HN' => "Honduras",
            'HK' => "Hong Kong",
            'HU' => "Hungary",
            'IS' => "Iceland",
            'IN' => "India",
            'ID' => "Indonesia",
            'IR' => "Iran",
            'IQ' => "Iraq",
            'IE' => "Ireland",
            'IM' => "Isle of Man",
            'IL' => "Israel",
            'IT' => "Italy",
            'JM' => "Jamaica",
            'JP' => "Japan",
            'JE' => "Jersey",
            'JO' => "Jordan",
            'KZ' => "Kazakhstan",
            'KE' => "Kenya",
            'KI' => "Kiribati",
            'KP' => "Korea (Democratic People's Republic of)",
            'KR' => "Korea (Republic of)",
            'KW' => "Kuwait",
            'KG' => "Kyrgyzstan",
            'LA' => "Laos",
            'LV' => "Latvia",
            'LB' => "Lebanon",
            'LS' => "Lesotho",
            'LR' => "Liberia",
            'LY' => "Libya",
            'LI' => "Liechtenstein",
            'LT' => "Lithuania",
            'LU' => "Luxembourg",
            'MO' => "Macao",
            'MK' => "Macedonia",
            'MG' => "Madagascar",
            'MW' => "Malawi",
            'MY' => "Malaysia",
            'MV' => "Maldives",
            'ML' => "Mali",
            'MT' => "Malta",
            'MH' => "Marshall Islands",
            'MQ' => "Martinique",
            'MR' => "Mauritania",
            'MU' => "Mauritius",
            'YT' => "Mayotte",
            'MX' => "Mexico",
            'FM' => "Micronesia",
            'MD' => "Moldova",
            'MC' => "Monaco",
            'MN' => "Mongolia",
            'ME' => "Montenegro",
            'MS' => "Montserrat",
            'MA' => "Morocco",
            'MZ' => "Mozambique",
            'MM' => "Myanmar",
            'NA' => "Namibia",
            'NR' => "Nauru",
            'NP' => "Nepal",
            'NL' => "Netherlands",
            'NC' => "New Caledonia",
            'NZ' => "New Zealand",
            'NI' => "Nicaragua",
            'NE' => "Niger",
            'NG' => "Nigeria",
            'NU' => "Niue",
            'NF' => "Norfolk Island",
            'MP' => "Northern Mariana Islands",
            'NO' => "Norway",
            'OM' => "Oman",
            'PK' => "Pakistan",
            'PW' => "Palau",
            'PS' => "Palestine",
            'PA' => "Panama",
            'PG' => "Papua New Guinea",
            'PY' => "Paraguay",
            'PE' => "Peru",
            'PH' => "Philippines",
            'PN' => "Pitcairn",
            'PL' => "Poland",
            'PT' => "Portugal",
            'PR' => "Puerto Rico",
            'QA' => "Qatar",
            'RE' => "Réunion",
            'RO' => "Romania",
            'RU' => "Russia",
            'RW' => "Rwanda",
            'BL' => "Saint Barthélemy",
            'SH' => "Saint Helena, Ascension and Tristan da Cunha",
            'KN' => "Saint Kitts and Nevis",
            'LC' => "Saint Lucia",
            'MF' => "Saint Martin (French part)",
            'PM' => "Saint Pierre and Miquelon",
            'VC' => "Saint Vincent and the Grenadines",
            'WS' => "Samoa",
            'SM' => "San Marino",
            'ST' => "Sao Tome and Principe",
            'SA' => "Saudi Arabia",
            'SN' => "Senegal",
            'RS' => "Serbia",
            'SC' => "Seychelles",
            'SL' => "Sierra Leone",
            'SG' => "Singapore",
            'SX' => "Sint Maarten (Dutch part)",
            'SK' => "Slovakia",
            'SI' => "Slovenia",
            'SB' => "Solomon Islands",
            'SO' => "Somalia",
            'ZA' => "South Africa",
            'GS' => "South Georgia and the South Sandwich Islands",
            'SS' => "South Sudan",
            'ES' => "Spain",
            'LK' => "Sri Lanka",
            'SD' => "Sudan",
            'SR' => "Suriname",
            'SJ' => "Svalbard and Jan Mayen",
            'SZ' => "Swaziland",
            'SE' => "Sweden",
            'CH' => "Switzerland",
            'SY' => "Syrian Arab Republic",
            'TW' => "Taiwan",
            'TJ' => "Tajikistan",
            'TZ' => "Tanzania",
            'TH' => "Thailand",
            'TL' => "Timor-Leste",
            'TG' => "Togo",
            'TK' => "Tokelau",
            'TO' => "Tonga",
            'TT' => "Trinidad and Tobago",
            'TN' => "Tunisia",
            'TR' => "Turkey",
            'TM' => "Turkmenistan",
            'TC' => "Turks and Caicos Islands",
            'TV' => "Tuvalu",
            'UG' => "Uganda",
            'UA' => "Ukraine",
            'AE' => "United Arab Emirates",
            'GB' => "United Kingdom",
            'US' => "United States",
            'UM' => "United States Minor Outlying Islands",
            'UY' => "Uruguay",
            'UZ' => "Uzbekistan",
            'VU' => "Vanuatu",
            'VE' => "Venezuela",
            'VN' => "Viet Nam",
            'VG' => "Virgin Islands (British)",
            'VI' => "Virgin Islands (U.S.)",
            'WF' => "Wallis and Futuna",
            'EH' => "Western Sahara",
            'YE' => "Yemen",
            'ZM' => "Zambia",
            'ZW' => "Zimbabwe"
        ];
    }

    public function isReady()
    {
        if ($this->state->mode == 'edit') {
            return false;
        }
        $isReady = true;
        foreach ($this->state->requiredFields as $field => $label) {
            if (!$this->state->$field) {
                $isReady = false;
            }
        }
        return $isReady;
    }

    public function editModeHandler( $args )
    {
        $this->state->mode = 'edit';
        return $this->renderRoot();
    }

    public function setAddressHandler( $args )
    {
        $this->state->addressLine1 = $args[ 'addressLine1' ];
        $this->state->addressLine2 = $args[ 'addressLine2' ];
        $this->state->townOrCity = $args[ 'townOrCity' ];
        $this->state->stateOrRegion = $args[ 'stateOrRegion' ];
        $this->state->postCode = $args[ 'postCode' ];
        $this->state->countryCode = strtoupper( $args[ 'countryCode' ] );
        $this->state->countryString = $this->getCountriesByISO()[ strtoupper($args[ 'countryCode' ]) ];

        foreach ($this->state->requiredFields as $req => $label) {
            if (!$args[ $req ]) {
                $this->parent->setFlashError( $label . " is a required field." );
                return $this->renderRoot();
            }
        }

        $this->state->mode = 'view';

        $this->parent->setAddressStatus( $this->isReady(), $this->state->countryCode );

        return $this->renderRoot();
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Address.twig" );
        $this->template = new TwigTemplate( $this, $tplTwig );
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        $this->state = new AddressState();
    }

    /**
     * Using $props and $this->state, optionally update state, optionally create child components via addOrUpdateChild().
     * @param array $props
     * @return void
     */
    protected function doUpdateState( $props )
    {
        $ready = $this->isReady();
        $this->parent->setAddressStatus( $ready, $this->state->countryCode );
        if (!$ready) {
            $this->state->mode = 'edit';
        }
    }

    protected function initComponent( $initConfig )
    {
        $this->testInputs(
            [
                'state' => [ 'PatternSeek\\ECommerce\\ViewState\\AddressState' ]
            ],
            $initConfig
        );
        $this->state = $initConfig[ 'state' ];
        $this->state->countryString = $this->getCountriesByISO()[ strtoupper( $this->state->countryCode ) ];
        // Allow the caller to set mode, but default to 'view'
        if (null === $this->state->mode) {
            $this->state->mode = 'view';
        }
    }
}