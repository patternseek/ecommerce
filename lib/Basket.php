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
use PatternSeek\ComponentView\ViewComponentResponse;
use PatternSeek\ECommerce\ViewState\BasketState;

/**
 * Class Basket
 */
class Basket extends AbstractViewComponent
{


    /**
     * @var BasketState
     */
    protected $state;

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        $this->state = new BasketState();
    }

    /**
     * Called after the page creating this view has
     * finished adding items and so on.
     * @param $initConfig
     * @return void
     * @throws \Exception
     * @internal param BasketConfig $config
     */
    public function initComponent( $initConfig )
    {
        $this->testInputs(
            [
                'config' => [ 'PatternSeek\ECommerce\BasketConfig' ],
                'vatRates' => [ 'array' ],
                'lineItems' => [ 'array' ]
            ],
            $initConfig
        );

        $config = $initConfig[ 'config' ];

        foreach ($initConfig[ 'lineItems' ] as $lineItem) {
            $this->state->lineItems[ ] = $lineItem;
        }

        $this->state->config = $config;
        $this->state->vatRates = $initConfig[ 'vatRates' ];
        $this->state->intro = $config->intro;

        $this->state->cardCountryCode = null;
        $this->state->addressCountryCode = null;

        $this->state->ipCountryCode = $this->geoIPCountryCode();
        $countryCode = $this->determineCountryCode();
        $this->state->countryCodeUsedForTransaction = $countryCode;

        $this->updateLineItemsAndTotal( $countryCode );

    }

    // Allows the payment provider to report the country code
    // for the user's card and returns the final 'winner' country
    public function confirmValidTxnFunc( $cardCountryCode )
    {
        if ($this->state->requireVATLocationProof == false) {
            return true;
        }
        $cardCountryCode = mb_strtolower( $cardCountryCode, 'UTF-8' );
        $this->state->cardCountryCode = $cardCountryCode;
        // Which country code do we have the most evidence for currently?
        $winnerCCode = $this->determineCountryCode();
        // Does the winner country code have at least
        // two pieces of identifying information?
        if (null !== $this->state->countryCodeConfirmed) {
            // Does the winner country code match the country code used
            // to calculate the original vat?
            // TODO: If not then the basket could be recalculated here
            // TODO: and re-presented to the user
            return ( $this->state->countryCodeUsedForTransaction == $winnerCCode );
        }else {
            // Not enough country ID info to continue
            return false;
        }
    }

    // Methods implementing AbstractComponentView

    /**
     * Using $this->state, optionally update state, optionally create child components via addOrUpdateChild(), return template props
     * @param $props
     * @return array Template props
     */
    protected function doUpdate( $props )
    {

        $this->state->validate();

        $tplProps = (array)$this->state;
        $tplProps[ 'paymentProviders' ] = [ ];

        foreach ($this->state->config->paymentProviders as $providerConfig) {
            $this->addOrUpdateChild(
                $providerConfig->name, $providerConfig->componentClass,
                [
                    'description' => $this->state->config->briefDescription,
                    'amount' => $this->state->total
                ],
                [
                    'config' => $providerConfig->conf,
                    'cardMustMatchCountryCode' => $this->state->ipCountryCode,
                    'buttonLabel' => null,
                    'email' => null,
                    'testMode' => true
                ] );
            $tplProps[ 'paymentProviders' ][ ] = $providerConfig->name;
        }
        // Template properties
        return $tplProps;
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Basket.twig" );

        $this->template = new TwigTemplate( $this, $tplTwig );
    }

    // HTTP accessible methods

    /**
     * Attempt to validate a european VAT
     * ** This method is called by exec() and is designed to be HTTP accessible **
     *
     * @param $args ['vatNumber'=>"...", 'countryCode'=>'...']
     * @return ViewComponentResponse
     * @throws \Exception
     */
    protected function validateVatNumberHandler( $args )
    {
        $this->testInputs(
            [
                'vatNumber' => [ 'string' ],
                'countryCode' => [ 'string' ]
            ],
            $args
        );

        $args[ 'vatNumber' ] = str_replace( ' ', '', $args[ 'vatNumber' ] );

        $this->state->vatNumber = null;
        $this->state->vatNumberStatus = null;
        $this->state->vatNumberCountryCode = null;

        $client = new \SoapClient( "http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl" );
        $soapResponse = null;
        try{
            $soapResponse = $client->checkVat( array(
                'countryCode' => $args[ 'countryCode' ],
                'vatNumber' => $args[ 'vatNumber' ]
            ) );
            if ($soapResponse->valid) {
                // Valid
                $this->state->vatNumber = $args[ 'vatNumber' ];
                $this->state->vatNumberStatus = 'valid';
                $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
            }else {
                // Invalid
                $this->state->vatNumber = null;
                $this->state->vatNumberCountryCode = null;
                $this->state->vatNumberStatus = 'invalid';
            }
        }catch( \SoapFault $e ){
            if ($e->getMessage() == "INVALID_INPUT") {
                // Invalid
                $this->state->vatNumber = null;
                $this->state->vatNumberCountryCode = null;
                $this->state->vatNumberStatus = 'invalid';
            }else {
                // Unknown due to technical error
                // We allow the vat number but flag it for manual checking
                $this->state->vatNumber = $args[ 'vatNumber' ];
                $this->state->vatNumberStatus = 'unknown';
                $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
            }

        }
        $this->updateLineItemsAndTotal( $this->determineCountryCode() );

        // Render full component
        return $this->renderRoot();
    }


    // Protected methods

    /**
     * @param $userCountryCode
     */
    protected function updateLineItemsAndTotal( $userCountryCode )
    {
        $remoteRate = $this->getVatRate( $userCountryCode );
        $total = 0;
        $this->state->requireVATLocationProof = false;
        /** @var LineItem $lineItem */
        foreach ($this->state->lineItems as $id => $lineItem) {
            $lineItem->remoteVatJusrisdictionCountryCode = $userCountryCode;

            // VAT number either verified or there was a
            // technical error so it is allowed but marked for manual check
            if (null !== $this->state->vatNumber) {
                $lineItem->isB2b = true;
                $lineItem->vatTypeCharged = 'b2b';
                $lineItem->vatPerItem = 0.0;
                // Line item VAT jurisdiction is local
            }elseif ($lineItem->vatJurisdictionType == 'local') {
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = $lineItem->netPrice * $this->state->config->localVatRate;
                $lineItem->vatTypeCharged = 'local';
                // Line item VAT jurisdiction is remote
            }else {
                $this->state->requireVATLocationProof = true;
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = $lineItem->netPrice * $remoteRate;
                $lineItem->vatTypeCharged = 'remote';
            }
            $total
                += ( $lineItem->netPrice
                    + ( $lineItem->vatPerItem?$lineItem->vatPerItem:0 )
                )
                * ( $lineItem->quantity?$lineItem->quantity:1 );
        }
        $this->state->total = (double)$total;
    }

    /**
     * @return string Two letter country code, lower case
     */
    protected function determineCountryCode()
    {
        $ipCountryCode = $this->state->ipCountryCode;
        $cardCountryCode = $this->state->cardCountryCode;
        $addressCountryCode = $this->state->addressCountryCode;

        $cCodes = [ ];
        foreach ([ $ipCountryCode, $cardCountryCode, $addressCountryCode ] as $cCode) {
            if (null === $cCode) {
                continue;
            }
            if (!isset( $cCodes[ $cCode ] )) {
                $cCodes[ $cCode ] = 0;
            }
            $cCodes[ $cCode ] ++;
        }
        arsort( $cCodes );
        $keys = array_keys( $cCodes );
        $winnerCCode = $keys[ 0 ];
        $winnerVotes = $cCodes[ $winnerCCode ];
        if ($winnerVotes >= 2) {
            $this->state->countryCodeConfirmed = $winnerCCode;
        }else {
            $this->state->countryCodeConfirmed = null;
        }
        return mb_strtolower( $winnerCCode, 'UTF-8' );
    }

    /**
     * @param
     * @return double
     */
    protected function getVatRate( $countryCode )
    {
        return ( (double)$this->state->vatRates[ 'rates' ][ mb_strtoupper( $countryCode,
                'UTF-8' ) ][ 'standard_rate' ] / 100 );
    }

    public function getVatCountries()
    {
        $ret = [ ];
        foreach ($this->state->vatRates[ 'rates' ] as $cc => $info) {
            if (isset( $info[ 'iso_duplicate_of' ] )) {
                continue;
            }
            $ret[ $cc ] = $info[ 'country' ];
        }
        asort( $ret );
        return $ret;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function geoIPCountryCode()
    {
        if (!extension_loaded( 'geoip' )) {
            throw new \Exception( "There is a configuration error in the application. The GeoIP extension is not loaded." );
        }
        return geoip_country_code_by_name( $this->state->config->remoteIp );
    }
}