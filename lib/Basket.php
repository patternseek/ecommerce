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
     * @var callable
     */
    public $transactionSuccessCallback;

    /**
     * @var BasketState
     */
    protected $state;

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
                'lineItems' => [ 'array' ],
                'testMode' => [ 'boolean' ]
            ],
            $initConfig
        );

        $config = $initConfig[ 'config' ];

        foreach ($initConfig[ 'lineItems' ] as $lineItem) {
            $this->state->lineItems[ ] = $lineItem;
        }

        $this->state->config = $config;

        // Set required billing address fields
        $this->state->config->billingAddress->requiredFields = [
            'addressLine1' => "Address line 1",
            'postCode' => "Post code",
            'countryCode' => "Country"
        ];

        $this->state->vatRates = $initConfig[ 'vatRates' ];
        $this->state->intro = $config->intro;
        $this->state->outro = $config->outro;
        $this->state->testMode = $initConfig[ 'testMode' ];

        $this->state->cardCountryCode = null;
        $this->state->addressCountryCode = null;

        $this->state->ipCountryCode = $this->geoIPCountryCode();

        $this->updateLineItemsAndTotal();
    }

    /**
     * Attempt to validate a european VAT
     * ** This method is called by exec() and is designed to be HTTP accessible **
     *
     * @param $args ['vatNumber'=>"...", 'countryCode'=>'...']
     * @return ViewComponentResponse
     * @throws \Exception
     */
    public function validateVatNumberHandler( $args )
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
                $this->setFlashError( "Sorry but the VAT number or country entered was incorrect." );
            }
        }catch( \SoapFault $e ){
            if ($e->getMessage() == "INVALID_INPUT") {
                // Invalid
                $this->state->vatNumber = null;
                $this->state->vatNumberCountryCode = null;
                $this->state->vatNumberStatus = 'invalid';
                $this->setFlashError( "Sorry but the VAT number or country entered was incorrect." );
            }else {
                // Unknown due to technical error
                // We allow the vat number but flag it for manual checking
                $this->state->vatNumber = $args[ 'vatNumber' ];
                $this->state->vatNumberStatus = 'unknown';
                $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
            }
        }
        $this->updateLineItemsAndTotal();

        // Render full component
        return $this->renderRoot();
    }

    /**
     * Provides a mechanism for a PaymentProvider component to check if it should charge the user
     * @param $cardCountryCode
     * @return bool
     */
    public function confirmValidTxnFunc( $cardCountryCode )
    {
        $this->state->cardCountryCode = $cardCountryCode;
        return $this->state->vatInfoOk();
    }

    /**
     * Allows the address component to indicate it's ready
     * @param boolean $isReady
     * @param string $countryCode
     * @param $addressString
     */
    public function setAddressStatus( $isReady, $countryCode, $addressString )
    {
        $this->state->addressReady = $isReady;
        $this->state->addressCountryCode = $countryCode;
        $this->state->addressAsString = $addressString;
    }

    /**
     * Returns a list of country names and ISO codes for EU countries
     * @return array
     */
    public function getVatCountries()
    {
        $ret = [ ];
        foreach ($this->state->vatRates[ 'rates' ] as $cc => $info) {
            if (isset( $info[ 'iso_duplicate' ] )) {
                continue;
            }
            $ret[ $cc ] = $info[ 'country' ];
        }
        asort( $ret );
        return $ret;
    }

    /**
     * @param Transaction $txn
     */
    public function transactionSuccess( Transaction $txn )
    {
        $this->state->complete = true;

        $txn->vatNumberStatus = $this->state->vatNumberStatus;
        $txn->vatNumberGiven = $this->state->vatNumber;
        $txn->vatNumberGivenCountryCode = $this->state->vatNumberCountryCode;
        $txn->transactionAmount = $this->state->total;
        $txn->billingAddressCountryCode = $this->state->addressCountryCode;
        $txn->ipCountryCode = $this->state->ipCountryCode;
        $txn->vatCalculationBaseOnCountryCode = $this->state->vatCalculatedBasedOnCountryCode;
        $txn->vatRateUsed = $this->state->getVatRate( $this->state->vatCalculatedBasedOnCountryCode );
        $txn->billingAddress = $this->state->addressAsString;
        $txn->transactionDescription = $this->state->config->briefDescription;
        $txn->transactionDetail = $this->state->transactionDetail;
        $txn->time = time();
        try{
            $txn->validate();
        }catch( \Exception $e ){
            $txn->validationError = $e->getMessage();
        }

        return $this->transactionSuccessCallback->__invoke( $txn );
    }

    /**
     * Used for testing state methods
     */
    function getStateForTesting()
    {
        return $this->state;
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

    /**
     */
    protected function updateLineItemsAndTotal()
    {
        if (!( $provisionalUserCountryCode = $this->state->getConfirmedCountryCode() )) {
            $provisionalUserCountryCode = $this->state->addressCountryCode;
        }
        $provisionalRemoteRate = $this->state->getVatRate( $provisionalUserCountryCode );
        $total = 0;
        $this->state->requireVATLocationProof = false;
        $this->state->transactionDetail = implode( ', ',
                [
                    "Quantity",
                    "Description",
                    "Net per item",
                    "VAT per item",
                    "VAT type"
                ] ) . "\n";
        /** @var LineItem $lineItem */
        foreach ($this->state->lineItems as $id => $lineItem) {
            $lineItem->remoteVatJusrisdictionCountryCode = $provisionalUserCountryCode;

            // VAT number either verified or there was a
            // technical error so it is allowed but marked for manual check
            if (null !== $this->state->vatNumber) {
                $lineItem->isB2b = true;
                $lineItem->vatTypeCharged = 'b2b';
                $lineItem->vatPerItem = 0.0;
                // Line item VAT jurisdiction is local
            }elseif ($lineItem->vatJurisdictionType == 'local') {
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = round( $lineItem->netPrice * $this->state->config->localVatRate, 2 );
                $lineItem->vatTypeCharged = 'local';
                // Line item VAT jurisdiction is remote
            }else {
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = round( $lineItem->netPrice * $provisionalRemoteRate, 2 );
                $lineItem->vatTypeCharged = 'remote';

                $this->state->requireVATLocationProof = true;
                $this->state->vatCalculatedBasedOnCountryCode = $provisionalUserCountryCode;
            }
            $total
                += ( $lineItem->netPrice
                    + ( $lineItem->vatPerItem?$lineItem->vatPerItem:0 )
                )
                * ( $lineItem->quantity?$lineItem->quantity:1 );
            $this->state->transactionDetail .= implode( ', ',
                    [
                        ( $lineItem->quantity?$lineItem->quantity:'-' ),
                        str_replace( "\n", " ", $lineItem->description ),
                        $lineItem->netPrice,
                        $lineItem->vatPerItem,
                        $lineItem->vatTypeCharged
                    ] ) . "\n";
        }
        $this->state->total = (double)$total;
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        $this->state = new BasketState();
    }

    /**
     * Using $this->state, optionally update state, optionally create child components via addOrUpdateChild(), return template props
     * @param $props
     * @return array Template props
     */
    protected function doUpdateState( $props )
    {

        // Callbacks must be provided on each update as they can't be serialised.
        $this->testInputs(
            [
                'transactionSuccessCallback' => [ 'callable' ]
            ],
            $props
        );

        $this->transactionSuccessCallback = $props[ 'transactionSuccessCallback' ];

        // Set up billing address
        $this->addOrUpdateChild(
            'billingAddress', '\\PatternSeek\\ECommerce\\Address',
            [ ],
            [
                'state' => $this->state->config->billingAddress
            ] );

        $this->updateLineItemsAndTotal();

        // Setup payment providers
        $this->state->paymentProviderNames = [ ];
        foreach ($this->state->config->paymentProviders as $providerConfig) {
            $this->addOrUpdateChild(
                $providerConfig->name, $providerConfig->componentClass,
                [
                    'description' => $this->state->config->briefDescription,
                    'amount' => $this->state->total,
                    'basketReady' => $this->state->readyForPaymentInfo(),
                    'transactionComplete' => $this->state->complete,
                    'address' => $this->childComponents[ 'billingAddress' ]->getState()
                ],
                [
                    'config' => $providerConfig->conf,
                    'cardMustMatchCountryCode' => $this->state->ipCountryCode,
                    'buttonLabel' => null,
                    'email' => null,
                    'testMode' => $this->state->testMode,
                ] );
            $this->state->paymentProviderNames[ ] = $providerConfig->name;
        }

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
}