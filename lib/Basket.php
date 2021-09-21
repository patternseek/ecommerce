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
use PatternSeek\ECommerce\Config\BasketConfig;
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
                'config' => [ BasketConfig::class ],
                'vatRates' => [ 'array' ],
                'lineItems' => [ 'array' ],
                'testMode' => [ 'boolean' ],
                'chargeMode' => [ 'string' ],
                'translations' => [BasketTranslations::class,new BasketTranslations()]
            ],
            $props
        );

        $config = $props[ 'config' ];
        $this->state->config = $config;
        
        $this->state->trans = $props['translations'];
        // Address is sharing the BasketTranslations object
        $this->state->config->billingAddress->trans = $props['translations'];


        LineItem::checkForDuplicateMetadataKeys( $props['lineItems'] );

        foreach ($props[ 'lineItems' ] as $lineItem) {
            $this->state->lineItems[ ] = $lineItem;
        }
        
        // Set required billing address fields
        $this->state->config->billingAddress->requiredFields = [
            'addressLine1' => $this->state->trans->address_line_1,
            'postCode' => $this->state->trans->postcode,
            'countryCode' => $this->state->trans->country
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
        $args[ 'vatNumber' ] = str_replace( '-', '', $args[ 'vatNumber' ] );
        $args[ 'vatNumber' ] = str_replace( '_', '', $args[ 'vatNumber' ] );
        $args[ 'vatNumber' ] = str_replace( '/', '', $args[ 'vatNumber' ] );

        $args[ 'vatNumber' ] = strtoupper( $args[ 'vatNumber' ] );

        $this->state->vatNumber = null;
        $this->state->vatNumberStatus = null;
        $this->state->vatNumberCountryCode = null;

        if( $args['countryCode'] == "GB" ){
            $this->validateGbVatNumber( $args );            
        }else{
            $this->validateEuVatNumber( $args );
        }
        
        
        $this->updateLineItemsAndTotal();

        // Render full component
        $this->updateState();
        return $this->render();
    }

    /**
     * @param $args
     */
    private function validateGbVatNumber( $args ){
        
        $vatUrl = $this->state->config->hmrcVatApiConfig->vatUrl;
        
        // Do VAT check
        $optsAr = [
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true, // Needed to get body of non-200 responses
                'header' => [
                    "Accept: application/vnd.hmrc.1.0+json",
                ]
            ]
        ];

        $context  = stream_context_create($optsAr);
        $lookupResRaw = file_get_contents( $vatUrl.urlencode($args['vatNumber']), false, $context );

        if( $lookupResRaw === false ){
            $this->log("Unexpected error from HMRC VAT API when attempting to verify VAT number: Connection failed or no response", LogLevel::ALERT);
            $this->vatCheckFailedDueToTechnicalError( $args );
            return;
        }
        $lookupRes = json_decode( $lookupResRaw );
        if (isset( $lookupRes->code )) {
            // Invalid
            $this->state->vatNumber = null;
            $this->state->vatNumberCountryCode = null;
            $this->state->vatNumberStatus = 'invalid';
            $this->setFlashError( $this->state->trans->invalid_vat_number );
            return;
        } 
        if( $lookupRes->target ){
            // Valid
            $this->state->vatNumber = $args[ 'vatNumber' ];
            $this->state->vatNumberStatus = 'valid';
            $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
            /** @noinspection PhpUnnecessaryReturnInspection */
            return;
        }else{
            // Not expecting this to match to log fatal
            $this->log( "Unexpected response from HMRC VAT API: {$lookupResRaw}", LogLevel::ALERT );
            $this->vatCheckFailedDueToTechnicalError( $args );
        }
        
        
    }
    
    /**
     * @param $args
     */
    private function validateEuVatNumber( $args ): void
    {
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
                $this->setFlashError( $this->state->trans->invalid_vat_number );
            }
        }catch( \SoapFault $e ){
            if ($e->getMessage() == "INVALID_INPUT") {
                // Invalid
                $this->state->vatNumber = null;
                $this->state->vatNumberCountryCode = null;
                $this->state->vatNumberStatus = 'invalid';
                $this->setFlashError( $this->state->trans->invalid_vat_number );
            }else {
                $this->vatCheckFailedDueToTechnicalError( $args );
            }
        }
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
//            if (isset( $info[ 'iso_duplicate' ] )) {
//                continue;
//            }
            $ret[ $cc ] = $this->state->trans->countriesByISO[ $cc ];
        }
        asort( $ret );
        return $ret;
    }
    
    /**
     * Provides information to the client about a transaction that is about to be attemped.
     * This method is called by the payment provides class once we're ready to begin the transaction.
     * 
     * @param $uid
     * @param $paymentMethodId
     * @param $amountCents
     * @param $currency
     * @param $description
     * @param $email
     * @param $lineItems
     */
    public function preTransactionNotification(
        $uid
    ){
        $this->state->transactionUid = $uid;
        
        $txn = new Transaction();
        $txn->complete = false;
        $txn->time = time();
        
        $this->populateTransactionDetails( $txn );
        
        $this->state->transactionSuccessCallback->preTransactionNotification( $txn );
    }

    /**
     * @param Transaction $txn
     * @return Response
     * @throws \Exception
     */
    public function transactionSuccess( Transaction $txn )
    {
        $this->state->complete = true;
        $txn->complete = true;
        $txn->uid = $this->state->transactionUid;

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
     * Used for testing state methods
     */
    public function getStateForTesting()
    {
        return $this->state;
    }

    /**
     * @return bool
     */
    public function getComplete(){
        return $this->state->complete;
    }

    /**
     * @param Transaction $txn
     */
    public function populateTransactionDetails( Transaction $txn )
    {
        $txn->uid = $this->state->transactionUid;
        $txn->testMode = $this->state->testMode;
        $txn->vatNumberStatus = $this->state->vatNumberStatus;
        $txn->vatNumberGiven = $this->state->vatNumber;
        $txn->vatNumberGivenCountryCode = $this->state->vatNumberCountryCode;
        $txn->transactionAmount = $this->state->total;
        $txn->transactionCurrency = $this->state->config->currencyCode;
        $txn->vatAmount = $this->state->vatTotal;
        $txn->billingAddressCountryCode = $this->state->addressCountryCode;
        $txn->clientEmail = $this->state->config->email;
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
                'transactionSuccessCallback' => [ TransactionSuccessCallback::class, null ]
            ],
            $props
        );

        if( $props['transactionSuccessCallback'] instanceof TransactionSuccessCallback  ){
            $this->state->transactionSuccessCallback = $props[ 'transactionSuccessCallback' ];
        }

        $this->addOrUpdateChild(
            'billingAddress', Address::class,
            [ 
                'state' => $this->state->config->billingAddress, 
                'template'=>$this->state->config->addressTemplate 
            ]
        );

        foreach ($this->state->config->paymentProviders as $providerConfig) {
            $this->addOrUpdateChild(
                $providerConfig->name,
                $providerConfig->componentClass,
                [
                    'config' => $providerConfig->conf,
                    'translations' => $providerConfig->translations,
                    'template' => $providerConfig->template,
                    'buttonLabel' => null,
                    'email' => $this->state->config->email,
                    'testMode' => $this->state->testMode,
                    'chargeMode' => $this->state->chargeMode,
                    'description' => $this->state->config->briefDescription,
                    'amount' => $this->state->total,
                    'basketReady' => $this->state->readyForPaymentInfo(),
                    'transactionComplete' => $this->state->complete,
                    'address' => $this->childComponents[ 'billingAddress' ]->getState(),
                    'lineItems' => $this->state->lineItems
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
        // Template can be overridden in config
        if( null !== $this->state->config->basketTemplate ){
            $tplTwig = $this->state->config->basketTemplate;
        }else{
            $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Basket.twig" );
        }
        $this->template = new TwigTemplate( $this, null, $tplTwig );
    }

    /**
     * VAT check status is unknown due to technical error
     * We allow the vat number but flag it for manual checking
     * @param $args
     */
    private function vatCheckFailedDueToTechnicalError( $args ): void
    {
        $this->state->vatNumber = $args[ 'vatNumber' ];
        $this->state->vatNumberStatus = 'unknown';
        $this->state->vatNumberCountryCode = $args[ 'countryCode' ];
    }
}
