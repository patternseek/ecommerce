<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PatternSeek\ECommerce\Test;

use PatternSeek\DependencyInjector\DependencyInjector;
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\BasketConfig;
use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\Stripe;
use PatternSeek\ECommerce\StripeFacade\StripeFacade;
use PatternSeek\ECommerce\StripeFacade\StripeTokenMock;
use Pimple\Container;

/**
 * Class BasketTest
 * @package PatternSeek\ECommerce\Test
 */
class BasketTest extends \PHPUnit_Framework_TestCase
{

    function setup(){
        DependencyInjector::init( new Container() );
    }

    protected $successCallback;
    protected $delayedSuccessCallback;
    protected $subscriptionSuccessCallback;

    public function testDelayedModeTransaction()
    {
        $billingAddress = $this->getUSAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $testDelayedSuccess = new TestDelayedSuccess();
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress, $chargeMode = "delayed", $testDelayedSuccess );

        StripeFacade::$testMode = true;
        $this->succeedOnDelayedTransaction( $view );

        $delayedTxn = $testDelayedSuccess->delayedTxn;

        // Charge again. Doesn't actually call Stripe of course but could catch something in future.
        $delayedTxn->charge( $this->getPaymentProvidersConfig() );
        $delayedTxn->charge( $this->getPaymentProvidersConfig() );
    }
    
    public function testSubscription()
    {
        $billingAddress = $this->getUSAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];

        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress, $chargeMode = "subscription" );

        StripeFacade::$testMode = true;
        $this->succeedOnSubscription( $view );

    }
    
    public function testElectronicServiceToUKConsumer()
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireUserLocationProof
        );
        $this->assertTrue(
            $state->getConfirmedUserCountryCode() == "GB" // UK address, UK IP
        );
        $this->assertTrue(
            $state->vatInfoOk() // Confirmed country code
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'GB'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 120.00, $state->total ); // 20% VAT to UK consumer of e-service
    }

    public function testNormalServiceToUKConsumer()
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getNormalServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireUserLocationProof // Not needed for normal services
        );
        $this->assertTrue(
            $state->getConfirmedUserCountryCode() == "GB" // UK address, UK IP
        );
        $this->assertTrue(
            $state->vatInfoOk() // Confirmed country code
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'GB'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 120.00, $state->total ); // 20% VAT to UK consumer of e-service
    }

    public function testElectronicServiceToEUConsumer()
    {
        $billingAddress = $this->getEUAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireUserLocationProof
        );
        $this->assertFalse(
            $state->getConfirmedUserCountryCode() // ES address, UK IP
        );
        $this->assertFalse(
            $state->vatInfoOk() // No confirmed country code
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'ES'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 121.00, $state->total ); // 21% VAT to Spanish consumer of e-service
    }

    public function testNormalServiceToEUConsumer()
    {
        $billingAddress = $this->getEUAddress();
        $lineItem = $this->getNormalServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireUserLocationProof // Not needed for normal services
        );
        $this->assertFalse(
            $state->getConfirmedUserCountryCode() // ES address, UK IP
        );
        $this->assertTrue(
            $state->vatInfoOk() // No confirmation needed for normal services
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'ES'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 120.00, $state->total ); // 20% VAT charged at vendor rate (UK) for normal service
    }

    /**
     * ROW businesses and consumers are indistinguishable
     * to us as non EU businesses have no EU VAT number.
     */
    public function testElectronicServiceToROWConsumerOrBusiness()
    {
        $billingAddress = $this->getUSAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireUserLocationProof
        );
        $this->assertFalse(
            $state->getConfirmedUserCountryCode()
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'US'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo()
        );
        $this->assertFalse(
            $state->vatInfoOk()
        );

        $this->assertEquals( 100.00, $state->total ); // No VAT to US consumer
    }

    /**
     * ROW businesses and consumers are indistinguishable
     * to us as non EU businesses have no EU VAT number.
     */
    public function testNormalServiceToROWConsumerOrBusiness()
    {
        $billingAddress = $this->getUSAddress();
        $lineItem = $this->getNormalServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireUserLocationProof // Not needed for normal services
        );
        $this->assertFalse(
            $state->getConfirmedUserCountryCode()
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'US'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo()
        );
        $this->assertTrue(
            $state->vatInfoOk() // No confirmation needed for normal services
        );
        $this->assertEquals( 100.00, $state->total ); // No VAT to US consumer
    }

    public function testElectronicServiceToUKBusiness()
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => "333289454" ] ); //BBC VAT number

        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireUserLocationProof
        );
        $this->assertTrue(
            $state->getConfirmedUserCountryCode() == "GB" // UK address, UK IP
        );
        $this->assertTrue(
            $state->vatInfoOk() // Confirmed country code
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'GB'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 120.00, $state->total ); // 20% VAT to UK consumer of e-service
    }

    public function testNormalServiceToUKBusiness()
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getNormalServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => "333289454" ] ); //BBC VAT number

        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireUserLocationProof // Not needed for normal services
        );
        $this->assertTrue(
            $state->getConfirmedUserCountryCode() == "GB" // UK address, UK IP
        );
        $this->assertTrue(
            $state->vatInfoOk() // Confirmed country code
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'GB'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 120.00, $state->total ); // 20% VAT to UK consumer of e-service
    }

    public function testElectronicServiceToEUBusiness()
    {
        $billingAddress = $this->getEUAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );

        $view->render( "validateVatNumber",
            [ "countryCode" => "ES", "vatNumber" => "a28015865" ] ); //Telefonica Spain VAT number

        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireUserLocationProof
        );
        $this->assertEquals(
            "ES",
            $state->getConfirmedUserCountryCode() // B2B with valid VAT number, so VAT number CC is authoritative
        );
        $this->assertTrue(
            $state->vatInfoOk() // No need to record location info for a business (despite address and IP not matching)
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'ES'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 100.00,
            $state->total ); // 0% VAT to Spanish (non GB, but in EU) business due to intra-community declarations
    }

    public function testNormalServiceToEUBusiness()
    {
        $billingAddress = $this->getEUAddress();
        $lineItem = $this->getNormalServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );

        $view->render( "validateVatNumber",
            [
                "countryCode" => "ES",
                "vatNumber" => "a28015865"
            ] ); //Telefonica Spain VAT number, deliberately lower case, would be rejected if not uppercased by Basket

        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireUserLocationProof // Not needed for normal services
        );
        $this->assertEquals(
            "ES",
            $state->getConfirmedUserCountryCode() // B2B with valid VAT number, so VAT number CC is authoritative
        );
        $this->assertTrue(
            $state->vatInfoOk() // No need to record location info for a business (despite address and IP not matching)
        );
        $this->assertTrue(
            $state->addressReady
        );
        $this->assertTrue(
            $state->addressCountryCode == 'ES'
        );
        $this->assertTrue(
            $state->readyForPaymentInfo() // Currently just checks address is ready
        );
        $this->assertEquals( 100.00,
            $state->total ); // 0% VAT to Spanish (non GB, but in EU) business due to intra-community declarations
    }

    function testUnserializeAndStripeFailures()
    {
        $billingAddress = $this->getUSAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress );
        $state = $view->getStateForTesting();

        $serialised = serialize( $view );
        /** @var Basket $unserialised */
        $unserialised = unserialize( $serialised );

        $unserialised->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );

        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->readyForPaymentInfo()
        );

        // Check stripe method fails
        StripeFacade::$testMode = true;

        /** @var Basket $uns */
        $unserialised = unserialize( $serialised );
        $this->failOn3DifferentCountries( $unserialised );
        /** @var Basket $uns */
        $unserialised = unserialize( $serialised );
        $this->failOnOnlyIPandCardMatch( $unserialised );
        /** @var Basket $uns */
        $unserialised = unserialize( $serialised );
        $this->succeedOnSameAddressAndCardCountries( $unserialised, $successOutput );
        /** @var Basket $uns */
        $unserialised = unserialize( $serialised );
        $this->succeedOnAllCountriesMatch( $unserialised, $successOutput );

    }

    /**
     * @return mixed
     */
    protected function getVatRates()
    {
        if( ! getenv('vatlayer_api_key') ){
            throw new \Exception( "Please set the vatlayer_api_key environment variable" );
        }
        
        if (!( $ratesStr = @file_get_contents( "/tmp/vatrates.json" ) )) {
            $apiKey = getenv('vatlayer_api_key');
            $ratesStr = file_get_contents( "http://apilayer.net/api/rate_list?access_key={$apiKey}&format=1" );
            file_put_contents( "/tmp/vatrates.json", $ratesStr );
        }
        $vatRates = json_decode( $ratesStr, true );
        return $vatRates;
    }

    /**
     * @param Basket $uns
     */
    protected function failOn3DifferentCountries( $uns )
    {
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'ES' ];

        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;

        // ES Card + US address + GB IP, shoud fail
        $this->assertTrue(
            false !== strstr( $execOut, "Sorry but we can't collect enough information about your location" )
        );
    }

    /**
     * @param Basket $uns
     */
    protected function failOnOnlyIPandCardMatch( $uns )
    {
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;

        // GB Card + US address + GB IP, shoud fail
        $this->assertTrue(
            false !== strstr( $execOut, "Sorry but we can't collect enough information about your location" )
        );
    }

    /**
     * @param Basket $uns
     * @throws \Exception
     */
    protected function succeedOnDelayedTransaction( $uns )
    {
        // US card + US address + GB IP, should succeed
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateView(
            [
                'delayedTransactionSuccessCallback' => $this->delayedSuccessCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;
        $expected = array (
            'delayedTxn' =>
                array (
                    'billingAddress' => 'addressLine1
addressLine2
townOrCity
stateOrRegion
postCode
United States',
                    'billingAddressCountryCode' => 'US',
                    'chargeID' => NULL,
                    'clientEmail' => NULL,
                    'clientName' => NULL,
                    'ipCountryCode' => 'GB',
                    'paymentCountryCode' => 'US',
                    'paymentType' => 'card',
                    'providerClass' => Stripe::class,
                    'storedToken' => 'TestStripeCustomerID',
                    'testMode' => true,
                    'time' => NULL,
                    'transactionAmount' => 100.0,
                    'transactionCurrency' => 'GBP',
                    'transactionDescription' => 'Brief description of basket contents.',
                    'transactionDetailLegacy' => NULL,
                    'transactionDetailRaw' => '[
    {
        "description": "Some online service",
        "netPrice": 100,
        "vatPerItem": 0,
        "vatTypeCharged": "zero",
        "isB2b": false,
        "quantity": 1,
        "productType": "electronicservices",
        "enjoyedInLocationType": "row"
    }
]',
                    'validationError' => NULL,
                    'vatAmount' => 0.0,
                    'vatNumberGiven' => NULL,
                    'vatNumberGivenCountryCode' => NULL,
                    'vatNumberStatus' => 'notchecked',
                ),
            'actualTxn' =>
                array (
                    'billingAddress' => 'addressLine1
addressLine2
townOrCity
stateOrRegion
postCode
United States',
                    'billingAddressCountryCode' => 'US',
                    'chargeID' => 'TestStripeID',
                    'clientEmail' => NULL,
                    'clientName' => NULL,
                    'ipCountryCode' => 'GB',
                    'paymentCountryCode' => 'US',
                    'paymentType' => 'card',
                    'testMode' => true,
                    'time' => NULL,
                    'transactionAmount' => 100.0,
                    'transactionCurrency' => 'GBP',
                    'transactionDescription' => 'Brief description of basket contents.',
                    'transactionDetailLegacy' => NULL,
                    'transactionDetailRaw' => '[
    {
        "description": "Some online service",
        "netPrice": 100,
        "vatPerItem": 0,
        "vatTypeCharged": "zero",
        "isB2b": false,
        "quantity": 1,
        "productType": "electronicservices",
        "enjoyedInLocationType": "row"
    }
]',
                    'vatAmount' => 0.0,
                    'vatNumberGiven' => NULL,
                    'vatNumberGivenCountryCode' => NULL,
                    'vatNumberStatus' => 'notchecked',
                ),
        );
        krsort( $expected );
        $expectedString = "<div id=\"component-basket\">\n    " . var_export( $expected, true ) . "\n</div>\n";
        $this->assertEquals( $expectedString, $execOut  );

    }

    /**
     * @param Basket $uns
     * @throws \Exception
     */
    protected function succeedOnSubscription( $uns )
    {
        // US card + US address + GB IP, should succeed
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateView(
            [
                'subscriptionSuccessCallback' => $this->subscriptionSuccessCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;

        $expected =     
            [
                'subscription' =>
                    [
                        'id' => 'TestStripeSubscriptionID',
                    ],
                'customer' =>
                    [
                        'id' => 'TestStripeCustomerID',
                    ],
            ];

        krsort( $expected );
        $expectedString = "<div id=\"component-basket\">\n    " . var_export( $expected, true ) . "\n</div>\n";
        $this->assertEquals( $expectedString, $execOut  );

    }
    
    /**
     * @param Basket $uns
     * @param $successOutput
     * @throws \Exception
     */
    protected function succeedOnSameAddressAndCardCountries( $uns, &$successOutput )
    {
        // US card + US address + GB IP, should succeed
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;
        $expected = [
            'clientName' => null,
            'billingAddress' => "addressLine1\naddressLine2\ntownOrCity\nstateOrRegion\npostCode\nUnited States",
            'clientEmail' => null,
            'transactionDescription' => 'Brief description of basket contents.',
            'transactionDetailLegacy' => null,
            'transactionDetailRaw' => '[
    {
        "description": "Some online service",
        "netPrice": 100,
        "vatPerItem": 0,
        "vatTypeCharged": "zero",
        "isB2b": false,
        "quantity": 1,
        "productType": "electronicservices",
        "enjoyedInLocationType": "row"
    }
]',
            'chargeID' => 'TestStripeID',
            'validationError' => NULL,
            'vatAmount' => 0.0,
            'paymentCountryCode' => 'US',
            'paymentType' => 'card',
            'testMode' => true,
            'vatNumberStatus' => 'notchecked',
            'vatNumberGiven' => null,
            'vatNumberGivenCountryCode' => null,
            'transactionAmount' => 100.0,
            'transactionCurrency' => "GBP",
            'billingAddressCountryCode' => 'US',
            'ipCountryCode' => 'GB',
            'time' => $successOutput[ 'time' ]
        ];
        ksort( $expected );
        $expectedString = "<div id=\"component-basket\">\n    " . var_export( $expected, true ) . "\n</div>\n";
        $this->assertEquals( $expectedString, $execOut  );

    }

    /**
     * @param Basket $uns
     * @param $successOutput
     */
    protected function succeedOnAllCountriesMatch( $uns, &$successOutput )
    {
        // GB card + GB address + GB IP, should succeed
        StripeTokenMock::$typeSetting = 'card';
        StripeTokenMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "billingAddress.setAddress", [
                'addressLine1' => 'addressLine1',
                'addressLine2' => 'addressLine2',
                'townOrCity' => 'townOrCity',
                'stateOrRegion' => 'stateOrRegion',
                'postCode' => 'postCode',
                'countryCode' => 'GB',
            ]
        )->content;

        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.submitForm", [ 'stripeToken' => "TESTTOKEN" ] )->content;

        $expected = [
            'clientName' => null,
            'billingAddress' => "addressLine1\naddressLine2\ntownOrCity\nstateOrRegion\npostCode\nUnited Kingdom",
            'clientEmail' => null,
            'transactionDescription' => 'Brief description of basket contents.',
            'transactionDetailLegacy' => null,
            'transactionDetailRaw' => '[
    {
        "description": "Some online service",
        "netPrice": 100,
        "vatPerItem": 20,
        "vatTypeCharged": "customer",
        "isB2b": false,
        "quantity": 1,
        "productType": "electronicservices",
        "enjoyedInLocationType": "local"
    }
]',
            'chargeID' => 'TestStripeID',
            'paymentCountryCode' => 'GB',
            'paymentType' => 'card',
            'testMode' => true,
            'vatNumberStatus' => 'notchecked',
            'vatNumberGiven' => null,
            'validationError' => NULL,
            'vatAmount' => 20.0,
            'vatNumberGivenCountryCode' => null,
            'transactionAmount' => 120.0,
            'transactionCurrency' => "GBP",
            'transactionAmount' => 120.0,
            'billingAddressCountryCode' => 'GB',
            'ipCountryCode' => 'GB',
            'time' => null
        ];
        ksort( $expected );
        $expectedString = "<div id=\"component-basket\">\n    " . var_export( $expected, true ) . "\n</div>\n";
        $this->assertEquals( $expectedString, $execOut  );

    }

    /**
     * @param LineItem $lineItem
     * @param $billingAddress
     * @param string $chargeMode
     * @param null $testDelayedSuccess
     * @return Basket
     * @throws \Exception
     */
    private function prepareBasket(
        LineItem $lineItem,
        $billingAddress,
        $chargeMode = "immediate",
        $testDelayedSuccess = null
    )
    {
        // This would usually come from a config source
        $configArray = [
            'localVatRate' => 0.20,
            'remoteIp' => "212.58.244.20", // A BBC server in the UK
            'currencyCode' => "GBP",
            'currencySymbol' => "£",
            'countryCode' => "GB",
            'briefDescription' => "Brief description of basket contents.",
            'intro' => "Optional intro HTML for page.",
            'paymentProviders' => $this->getPaymentProvidersConfig(),
            'billingAddress' => $billingAddress
        ];
        file_put_contents( "/tmp/cnf", yaml_emit( $configArray, YAML_UTF8_ENCODING ) );

        /** @var BasketConfig $config */
        $config = BasketConfig::fromArray( $configArray );

        $config->intro = "An intro";
        $config->outro = "An outro";

        $config->validate();

        $vatRates = $this->getVatRates();

//        $initConfig = [
//            'config' => $config,
//            'vatRates' => $vatRates,
//            'lineItems' => [ $lineItem ],
//            'testMode' => true
//        ];

        /** @var \PatternSeek\ECommerce\Basket $view */
        $view = new Basket();

        $this->successCallback = new TestSuccess();
        if( null === $testDelayedSuccess ){
            $testDelayedSuccess = new TestDelayedSuccess();
        }
        $this->delayedSuccessCallback = $testDelayedSuccess;
        $this->subscriptionSuccessCallback = new TestSubscriptionSuccess();

        $view->updateView(
            [
                'config' => $config,
                'vatRates' => $vatRates,
                'lineItems' => [ $lineItem ],
                'testMode' => true,
                'chargeMode' => $chargeMode,
                'transactionSuccessCallback' => $this->successCallback,
                'delayedTransactionSuccessCallback' => $this->delayedSuccessCallback,
                'subscriptionSuccessCallback' => $this->subscriptionSuccessCallback
            ]
        );

        $view->render();
        return $view;
    }

    /**
     * @return array
     */
    private function getUSAddress()
    {
        return [
            'addressLine1' => 'addressLine1',
            'addressLine2' => 'addressLine2',
            'townOrCity' => 'townOrCity',
            'stateOrRegion' => 'stateOrRegion',
            'postCode' => 'postCode',
            'countryCode' => 'US',
            'requiredFields' => [
                'addressLine1' => "Address line 1",
                'postCode' => "Post code",
                'countryCode' => "Country"
            ]
        ];
    }

    /**
     * @return array
     */
    private function getEUAddress()
    {
        return [
            'addressLine1' => 'addressLine1',
            'addressLine2' => 'addressLine2',
            'townOrCity' => 'townOrCity',
            'stateOrRegion' => 'stateOrRegion',
            'postCode' => 'postCode',
            'countryCode' => 'ES',
            'requiredFields' => [
                'addressLine1' => "Address line 1",
                'postCode' => "Post code",
                'countryCode' => "Country"
            ]
        ];
    }

    /**
     * @return array
     */
    private function getUKAddress()
    {
        return [
            'addressLine1' => 'addressLine1',
            'addressLine2' => 'addressLine2',
            'townOrCity' => 'townOrCity',
            'stateOrRegion' => 'stateOrRegion',
            'postCode' => 'postCode',
            'countryCode' => 'GB',
            'requiredFields' => [
                'addressLine1' => "Address line 1",
                'postCode' => "Post code",
                'countryCode' => "Country"
            ]
        ];
    }

    /**
     * @return LineItem
     */
    private function getElectronicServiceLineItem()
    {
        $lineItem = new LineItem();
        $lineItem->description = "Some online service";
        $lineItem->netPrice = 100.00;
        $lineItem->quantity = 1;
        $lineItem->productType = "electronicservices";
        return $lineItem;
    }

    /**
     * @return LineItem
     */
    private function getNormalServiceLineItem()
    {
        $lineItem = new LineItem();
        $lineItem->description = "Some non-online service";
        $lineItem->netPrice = 100.00;
        $lineItem->quantity = 1;
        $lineItem->productType = "normalservices";
        return $lineItem;
    }

    /**
     * @return array
     */
    private function getPaymentProvidersConfig()
    {
        return [
            'Stripe' => [
                'name' => 'stripe',
                'componentClass' => "\\PatternSeek\\ECommerce\\Stripe",
                'conf' => [
                    'testApiPubKey' => 'pk_test_abc123',
                    'testApiPrivKey' => 'sk_test_abc123',
                    'liveApiPubKey' => 'pk_live_abc123',
                    'liveApiPrivKey' => 'sk_live_abc123',
                    'siteName' => 'example.com',
                    'currency' => 'GBP',
                    'siteLogo' => '//example.com/logo.png'
                ]
            ]
        ];
    }
}