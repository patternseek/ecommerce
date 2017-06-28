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
use PatternSeek\ComponentView\Response;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\ViewState\BasketState;
use Psr\Log\LogLevel;

/**
 * Class Basket
 */
class Basket extends AbstractViewComponent
{

    /**
     * @var BasketState
     */
    protected $state;

    public function init( $props )
    {
        $this->testInputs(
            [
                'config' => [ 'PatternSeek\ECommerce\BasketConfig' ],
                'vatRates' => [ 'array' ],
                'lineItems' => [ 'array' ],
                'testMode' => [ 'boolean' ],
                'chargeMode' => [ 'string' ]
            ],
            $props
        );

        $config = $props[ 'config' ];

        foreach ($props[ 'lineItems' ] as $lineItem) {
            $this->state->lineItems[ ] = $lineItem;
        }

        $this->state->config = $config;

        // Set required billing address fields
        $this->state->config->billingAddress->requiredFields = [
            'addressLine1' => "Address line 1",
            'postCode' => "Post code",
            'countryCode' => "Country"
        ];

        $this->state->vatRates = $props[ 'vatRates' ];
        $this->state->intro = $config->intro;
        $this->state->outro = $config->outro;
        $this->state->testMode = $props[ 'testMode' ];
        $this->state->chargeMode = $props[ 'chargeMode' ];

        $this->state->cardCountryCode = null;
        $this->state->addressCountryCode = null;

        $this->state->ipCountryCode = $this->geoIPCountryCode();

        $this->updateLineItemsAndTotal();
        
        $this->state->initialised = true;
    }

    /**
     * Attempt to validate a european VAT
     * ** This method is called by exec() and is designed to be HTTP accessible **
     *
     * @param $args ['vatNumber'=>"...", 'countryCode'=>'...']
     * @return Response
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

        $args[ 'vatNumber' ] = strtoupper( $args[ 'vatNumber' ] );

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
        $this->updateState();
        return $this->render();
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
        
        $this->updateLineItemsAndTotal();
        
    }

    /**
     * Returns a list of country names and ISO codes for EU countries
     * @return array
     */
    public function getEUVatCountries()
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
     * @return Response
     */
    public function transactionSuccess( Transaction $txn )
    {
        $this->state->complete = true;

        $this->populateTransactionDetails( $txn ); 
        
        $txn->time = time();
        try{
            $txn->validate();
        }catch( \Exception $e ){
            $txn->validationError = $e->getMessage();
        }

        $this->state->successMessage = $this->state->transactionSuccessCallback->__invoke( $txn, $this )->content;
        // Render full component, including parent of basket, if any.
        $root = $this->getRootComponent();
        $root->updateState();
        return $root->render();
    }

    /**
     * @param DelayedOrRepeatTransaction $txn
     * @return Response
     */
    public function delayedTransactionSuccess( DelayedOrRepeatTransaction $txn )
    {
        $this->state->complete = true;

        $txn->time = time();
        try{
            $txn->validate();
        }catch( \Exception $e ){
            $txn->validationError = $e->getMessage();
        }

        $this->state->successMessage = $this->state->delayedTransactionSuccessCallback->__invoke( $txn, $this )->content;
        // Render full component, including parent of basket, if any.
        $root = $this->getRootComponent();
        $root->updateState();
        return $root->render();
    }

    /**
     * Used for testing state methods
     */
    public function getStateForTesting()
    {
        return $this->state;
    }

    /**
     * @param Transaction $txn
     */
    public function populateTransactionDetails( Transaction $txn )
    {
        $txn->testMode = $this->state->testMode;
        $txn->vatNumberStatus = $this->state->vatNumberStatus;
        $txn->vatNumberGiven = $this->state->vatNumber;
        $txn->vatNumberGivenCountryCode = $this->state->vatNumberCountryCode;
        $txn->transactionAmount = $this->state->total;
        $txn->transactionCurrency = $this->state->config->currencyCode;
        $txn->vatAmount = $this->state->vatTotal;
        $txn->billingAddressCountryCode = $this->state->addressCountryCode;
        $txn->ipCountryCode = $this->state->ipCountryCode;
        $txn->billingAddress = $this->state->addressAsString;
        $txn->transactionDescription = $this->state->config->briefDescription;
        $txnDetailArr = [ ];
        foreach ($this->state->lineItems as $item) {
            // Coerce LineItem StructClass to array
            $txnDetailArr[] = (array)$item;
        }
        $txn->setTransactionDetail( $txnDetailArr );
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
        $countryCode = geoip_country_code_by_name( $this->state->config->remoteIp );
        if( false === $countryCode ){
            return null;
        }
        return $countryCode;
    }

    /**
     */
    protected function updateLineItemsAndTotal()
    {
        if (!( $provisionalUserCountryCode = $this->state->getConfirmedUserCountryCode() )) {
            $provisionalUserCountryCode = $this->state->addressCountryCode;
        }
        $provisionalRemoteRate = $this->state->getVatRate( $provisionalUserCountryCode );
        $total = 0;
        $vatTotal = 0;
        $this->state->requireUserLocationProof = false;

        /** @var LineItem $lineItem */
        foreach ($this->state->lineItems as $id => $lineItem) {
            // VAT number either verified or there was a
            // technical error so it is allowed but marked for manual check
            $lineItem->isB2b = ( null !== $this->state->vatNumber );

            if ($provisionalUserCountryCode == $this->state->config->countryCode) {
                $lineItem->enjoyedInLocationType = 'local';
            }elseif (in_array( $provisionalUserCountryCode, array_keys( $this->getEUVatCountries() ) )) {
                $lineItem->enjoyedInLocationType = 'eu';
            }else {
                $lineItem->enjoyedInLocationType = 'row';
            }
            $lineItem->calculateVat( $this->state->config->localVatRate, $provisionalRemoteRate );
            
            $this->log( "Line item after VAT calculation: ".var_export( $lineItem, true ), LogLevel::DEBUG );

            $this->state->provisionalUserCountryCode = $provisionalUserCountryCode;
            if ($lineItem->productType == "electronicservices") {
                $this->state->requireUserLocationProof = true;
            }

            $lineItemTotal = $lineItem->getTotal();
            $total += $lineItemTotal;
            $vatTotal += $lineItem->getTotalVAT();

            $lineItem->validate();
        }
        $this->state->total = (double)$total;
        $this->state->vatTotal = (double)$vatTotal;
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        $this->state = new BasketState();
    }

    protected function updateState()
    {
        $props = $this->props;

        if( ! $this->state->initialised ){
            $this->init( $props );
        }

        $this->testInputs(
            [
                'transactionSuccessCallback' => [ '\\PatternSeek\\ECommerce\\TransactionSuccessCallback', null ],
                'delayedTransactionSuccessCallback' => [ '\\PatternSeek\\ECommerce\\DelayedTransactionSuccessCallback', null ]
            ],
            $props
        );

        if( $props['transactionSuccessCallback'] instanceof TransactionSuccessCallback  ){
            $this->state->transactionSuccessCallback = $props[ 'transactionSuccessCallback' ];
        }

        if( $props['delayedTransactionSuccessCallback'] instanceof DelayedTransactionSuccessCallback  ){
            $this->state->delayedTransactionSuccessCallback = $props[ 'delayedTransactionSuccessCallback' ];
        }
        
        $this->addOrUpdateChild(
            'billingAddress', '\\PatternSeek\\ECommerce\\Address',
            [ 'state' => $this->state->config->billingAddress ]
        );

        foreach ($this->state->config->paymentProviders as $providerConfig) {
            $this->addOrUpdateChild(
                $providerConfig->name,
                $providerConfig->componentClass,
                [
                    'config' => $providerConfig->conf,
                    'buttonLabel' => null,
                    'email' => $this->state->config->email,
                    'testMode' => $this->state->testMode,
                    'chargeMode' => $this->state->chargeMode,
                    'description' => $this->state->config->briefDescription,
                    'amount' => $this->state->total,
                    'basketReady' => $this->state->readyForPaymentInfo(),
                    'transactionComplete' => $this->state->complete,
                    'address' => $this->childComponents[ 'billingAddress' ]->getState()
                ]
            );
        }
        
        $this->updateLineItemsAndTotal();
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Basket.twig" );

        $this->template = new TwigTemplate( $this, null, $tplTwig );
    }
}
