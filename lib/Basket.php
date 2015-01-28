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
     * @var BasketConfig
     */
    protected $config;

    protected $vatRates;

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
        $vatRates = $initConfig[ 'vatRates' ];

        foreach ($initConfig[ 'lineItems' ] as $lineItem) {
            $this->addLineItem( $lineItem );
        }

        if (isset( $this->state->initialised ) && ( $this->state->initialised === true )) {
            return;
        }

        $this->state->config = $config;
        $this->vatRates = $vatRates;
        $this->state->intro = $config->intro;

        $this->state->cardCountryCode = null;
        if (!isset( $this->state->addressCountryCode )) {
            $this->state->addressCountryCode = null;
        }
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
     * @return array Template props
     */
    protected function doUpdate()
    {

        $this->state->validate();

        $tplProps = (array)$this->state;
        $tplProps[ 'paymentProviders' ] = [ ];

        foreach ($this->state->config->paymentProviders as $providerConfig) {
            $this->addOrUpdateChild(
                $providerConfig->name,
                $providerConfig->componentClass,
                [
                    'config' => $providerConfig->conf,
                    'cardMustMatchCountryCode' => $this->state->ipCountryCode,
                    'buttonLabel' => null,
                    'email' => null,
                    'amount' => $this->state->total,
                    'description' => $this->state->config->briefDescription,
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
                unset( $this->state->vatNumber );
                unset( $this->state->vatNumberCountryCode );
                $this->state->vatNumberStatus = 'invalid';
            }
        }catch( \SoapFault $e ){
            // Unknown due to technical error
            // We allow the vat number but flag it for manual checking
            $this->state->vatNumber = $args[ 'vatNumber' ];
            $this->state->vatNumberStatus = 'unknown';
            $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
        }
        // Render full component
        return $this->renderRoot();
    }

    // Public methods

    /**
     * @param LineItem $item
     * @throws \Exception
     */
    public function addLineItem( LineItem $item )
    {
        // Require some fields
        if (!( $item->description && $item->netPrice && $item->vatJurisdictionType )) {
            throw new \Exception( "LineItem objects added to Basket must include description, net price and vat jurisdiction type." );
        }
        $this->state->lineItems[ ] = $item;
    }

    /**
     * @param $id
     * @param LineItem $item
     */
    public function updateLineItem( $id, LineItem $item )
    {
        $this->state->lineItems[ $id ] = $item;
    }

    /**
     * @param $id
     */
    public function removeLineItem( $id )
    {
        unset( $this->state->lineItems[ $id ] );
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
            if (isset( $this->state->vatNumber )) {
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
                $this->state->requireVATLocationProof = true;
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = $lineItem->netPrice * $remoteRate;
                $lineItem->vatTypeCharged = 'remote';
            }
            $total += ( $lineItem->netPrice
                    + ( $lineItem->vatPerItem?$lineItem->vatPerItem:1 )
                )
                * ( $lineItem->quantity?$lineItem->quantity:1 );
        }
        $this->state->total = $total;
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
        return ( (double)$this->vatRates[ 'rates' ][ mb_strtoupper( $countryCode,
                'UTF-8' ) ][ 'standard_rate' ] / 100 );
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