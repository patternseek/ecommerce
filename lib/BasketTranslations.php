<?php
/**
 *
 * © 2019 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce;

use PatternSeek\StructClass\StructClass;

class BasketTranslations extends StructClass
{
    public $billing_address = "Billing address";
    public $address_line_1 = "Address line 1";
    public $address_line_2 = "Address line 2";
    public $town_or_city = "Town or city";
    public $state_or_region = "State or region";
    public $postcode = "Postcode";
    public $country = "Country";
    public $edit_address = "Edit";
    public $select = "Select...";
    public $continue = "Continue...";
    public $invalid_vat_number = "Sorry but the VAT number or country entered was incorrect.";
    public $quantity = "Qty.";
    public $description = "Description";
    #public $net_per_item = "Net per item";
    #public $vat_per_item = "VAT per item";
    public $total_vat = "VAT";
    public $total_net = "Net";
    public $total = "Total";
    public $eu_vat_number = "UK/EU VAT number";
    public $update = "Add or update VAT number";
    public $for_vat_regged_company = "If you are purchasing for a VAT-registered company, please enter its EU or UK VAT number here. This is optional but is necessary for your payment to be treated as a B2B transaction and/or if reverse charge applies.";
    public $this_transaction_has_been_completed = "This transaction has been completed.";
    public $uk_eu_vat_number_without_country_code = "UK/EU VAT number, <em>without country code</em>";
    public $vat_number_country = "VAT number country";
    
    public $is_a_required_field = " is a required field.";
    
    public $countriesByISO = [
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