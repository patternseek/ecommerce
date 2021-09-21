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
use PatternSeek\ECommerce\Config\BasketConfig;
use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;
use PatternSeek\ECommerce\Stripe\Facade\StripePaymentMethodMock;
use PatternSeek\ECommerce\Stripe\Stripe;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;

/**
 * Class BasketTest
 * @package PatternSeek\ECommerce\Test
 */
class BasketTest extends TestCase
{

    private $validUkVatNumber;
    /**
     * @var TestLogger
     */
    private $testLogger;

    function setup() : void {
        DependencyInjector::init( new Container() );
        $this->testLogger = new TestLogger();
    }
    
    protected function tearDown() : void{
        if( $this->testLogger->hasRecords( LogLevel::WARNING )
            || $this->testLogger->hasRecords( LogLevel::ERROR )
            || $this->testLogger->hasRecords( LogLevel::ALERT )
            || $this->testLogger->hasRecords( LogLevel::EMERGENCY )
            || $this->testLogger->hasRecords( LogLevel::CRITICAL )
        ){
            print_r( $this->testLogger->recordsByLevel[LogLevel::WARNING]);
            print_r( $this->testLogger->recordsByLevel[LogLevel::ERROR]);
            print_r( $this->testLogger->recordsByLevel[LogLevel::EMERGENCY]);
            print_r( $this->testLogger->recordsByLevel[LogLevel::ALERT]);
            print_r( $this->testLogger->recordsByLevel[LogLevel::CRITICAL]);
        }
    }

    protected $successCallback;

    
    public function testSubscription($passTemplatesAsConfig = false)
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $lineItem->subscriptionTypeId = "example-subscription-id";
        $successOutput = [ ];

        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress, $chargeMode = "subscription", $passTemplatesAsConfig );

        StripeFacade::$testMode = true;
        $this->succeedOnSubscription( $view );

    }
    
    public function testSubscriptionWithPassedTemplates()
    {
        $view = $this->testSubscription(true);
    }
    
    public function testElectronicServiceToUKConsumerWithPassedTemplates()
    {
        $view = $this->testElectronicServiceToUKConsumer(true);
    }
    
    public function testElectronicServiceToUKConsumer($passTemplatesAsConfig = false)
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $billingAddress, 'immediate', $passTemplatesAsConfig );
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

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => $this->validUkVatNumber ] );

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
            $state->vatNumberStatus == "valid"
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

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => $this->validUkVatNumber ] );

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
            $state->vatNumberStatus == "valid"
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
    
    function testFailOnDuplicateNonSubscriptionLineItemMetadata(){
        $billingAddress = $this->getUSAddress();
        $lineItem1 = $this->getElectronicServiceLineItem();
        $lineItem2 = $this->getElectronicServiceLineItem();
        
        $lineItem1->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        $lineItem2->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        
        $failed = false;
        try{
            /** @var Basket $view */
            $view = $this->prepareBasket( [ $lineItem1, $lineItem2 ], $billingAddress );
            // Should have thrown exception
            $failed = true;
        }catch( \Exception $e ){
            // Success
            $this->assertTrue(true);
        }
        if($failed){
            $this->fail("Failed to throw exception on duplicate metadata keys");
        }
    }
    
    function testFailOnDuplicateNonSubscriptionLineItemPassedWithSubscriptionMetadata(){
        $billingAddress = $this->getUSAddress();
        $lineItem1 = $this->getElectronicServiceLineItem();
        $lineItem2 = $this->getElectronicServiceLineItem();
        $lineItem3 = $this->getElectronicServiceLineItem();
        
        $lineItem1->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        $lineItem2->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        
        $lineItem3->subscriptionTypeId = "sub3";
        
        $failed = false;
        try{
            /** @var Basket $view */
            $view = $this->prepareBasket( [ $lineItem1, $lineItem2, $lineItem3 ], $billingAddress );
            // Should have thrown exception
            $failed = true;
        }catch( \Exception $e ){
            // Success
            $this->assertTrue(true);
        }
        if($failed){
            $this->fail("Failed to throw exception on duplicate metadata keys");
        }
    }
    
    function testSucceedOnDuplicateSubscriptionLineItemMetadata(){
        $billingAddress = $this->getUSAddress();
        $lineItem1 = $this->getElectronicServiceLineItem();
        $lineItem2 = $this->getElectronicServiceLineItem();
        
        $lineItem1->subscriptionTypeId = "sub1";
        $lineItem2->subscriptionTypeId = "sub2";
        
        $lineItem1->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        $lineItem2->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        
        /** @var Basket $view */
        $view = $this->prepareBasket( [ $lineItem1, $lineItem2 ], $billingAddress );
        
        // No exception means success
        $this->assertTrue(true);
    }
    
    function testSucceedOnDuplicateSubscriptionAndNonSubscriptionLineItemMetadata(){
        $billingAddress = $this->getUSAddress();
        $lineItem1 = $this->getElectronicServiceLineItem();
        $lineItem2 = $this->getElectronicServiceLineItem();
        
        $lineItem1->subscriptionTypeId = "sub1";
        
        $lineItem1->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        $lineItem2->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        
        /** @var Basket $view */
        $view = $this->prepareBasket( [ $lineItem1, $lineItem2 ], $billingAddress );
        
        // No exception means success
        $this->assertTrue(true);
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
        StripePaymentMethodMock::$typeSetting = 'card';
        StripePaymentMethodMock::$cardCountrySetting = (object)[ 'country' => 'ES' ];

        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.completion", [ 'paymentIntentId' => "TestStripeID" ] )->content;

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
        StripePaymentMethodMock::$typeSetting = 'card';
        StripePaymentMethodMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.completion", [ 'paymentIntentId' => "TestStripeID" ] )->content;

        // GB Card + US address + GB IP, shoud fail
        $this->assertTrue(
            false !== strstr( $execOut, "Sorry but we can't collect enough information about your location" )
        );
    }


    
    /**
     * @param Basket $uns
     * @throws \Exception
     */
    protected function succeedOnSubscription( $uns )
    {
        // US card + US address + GB IP, should succeed
        StripePaymentMethodMock::$typeSetting = 'card';
        StripePaymentMethodMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.completion", [ 'paymentIntentId' => "TestStripeID" ] )->content;

        $expected =
            array (
                'vatNumberStatus' => 'notchecked',
                'vatNumberGivenCountryCode' => NULL,
                'vatNumberGiven' => NULL,
                'vatAmount' => 20.0,
                'uid' => NULL,
                'validationError' => NULL,
                'transactionDetailRaw' => '[
    {
        "description": "Some online service",
        "netPrice": 100,
        "vatPerItem": 20,
        "vatTypeCharged": "customer",
        "isB2b": false,
        "quantity": 1,
        "productType": "electronicservices",
        "enjoyedInLocationType": "local",
        "subscriptionTypeId": "example-subscription-id",
        "couponCode": null,
        "vatRate": 0.2,
        "metadata": {
            "somekey": "1",
            "otherkey": "2"
        }
    }
]',
                'transactionDetailLegacy' => NULL,
                'transactionDescription' => 'Brief description of basket contents.',
                'transactionCurrency' => 'GBP',
                'transactionAmount' => 120.0,
                'time' => NULL,
                'testMode' => true,
                'subscriptionsRaw' => '[
    {
        "providerRawResult": {
            "customer": {
                "id": "TestStripeCustomerID"
            },
            "subscription": {
                "id": "TestStripeSubscriptionID",
                "customer": "TestStripeCustomerID",
                "latest_invoice": "TestStripeID"
            },
            "first_invoice": {
                "id": "TestStripeID"
            }
        }
    }
]',

                'paymentType' => 'card',
                'paymentCountryCode' => 'US',
                'ipCountryCode' => 'GB',
                'complete' => true,
                'clientName' => NULL,
                'clientEmail' => NULL,
                'chargeID' => 'TestStripeID',
                'billingAddressCountryCode' => 'GB',
                'billingAddress' => 'addressLine1
addressLine2
townOrCity
stateOrRegion
postCode
United Kingdom',
            );



        ksort( $expected );
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
        StripePaymentMethodMock::$typeSetting = 'card';
        StripePaymentMethodMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateView(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );
        $execOut = $uns->render( "stripe.completion", [ 'paymentIntentId' => "TestStripeID" ] )->content;
        $expected = [
            'complete' => true,
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
        "enjoyedInLocationType": "row",
        "subscriptionTypeId": null,
        "couponCode": null,
        "vatRate": 0,
        "metadata": {
            "somekey": "1",
            "otherkey": "2"
        }
    }
]',
            'chargeID' => 'TestStripeID',
            'uid' => NULL,
            'validationError' => NULL,
            'vatAmount' => 0.0,
            'paymentCountryCode' => 'US',
            'paymentType' => 'card',
            'subscriptionsRaw' => NULL,
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
        StripePaymentMethodMock::$typeSetting = 'card';
        StripePaymentMethodMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
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
        $execOut = $uns->render( "stripe.completion", [ 'paymentIntentId' => "TestStripeID" ] )->content;

        $expected = [
            'complete' => true,
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
        "enjoyedInLocationType": "local",
        "subscriptionTypeId": null,
        "couponCode": null,
        "vatRate": 0.2,
        "metadata": {
            "somekey": "1",
            "otherkey": "2"
        }
    }
]',
            'chargeID' => 'TestStripeID',
            'paymentCountryCode' => 'GB',
            'paymentType' => 'card',
            'subscriptionsRaw' => NULL,
            'testMode' => true,
            'vatNumberStatus' => 'notchecked',
            'vatNumberGiven' => null,
            'validationError' => NULL,
            'uid' => NULL,
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
     * @param LineItem|LineItem[] $lineItemOrArr
     * @param $billingAddress
     * @param string $chargeMode
     * @param bool $passTemplatesAsConfig
     * @return Basket
     * @throws \Exception
     */
    private function prepareBasket(
        $lineItemOrArr,
        $billingAddress,
        string $chargeMode = "immediate",
        bool $passTemplatesAsConfig = false
    )
    {
        if( ! getenv('hmrc_use_live_api') ){
            throw new \Exception( "Please set the hmrc_use_live_api environment variable" );
        }
        
        $useHmrcLiveApi = strtolower(getenv('hmrc_use_live_api')); 
        if( $useHmrcLiveApi === "true" ){
            $vatUrl = "https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/";
            $this->validUkVatNumber = "245719348"; // BT's VAT number
        }else{
            $vatUrl = "https://test-api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/";
            $this->validUkVatNumber = "166804280212"; // 166804280212 is a test vat number for use with the HMRC VAT API test environment
        }
        
            
        // This would usually come from a config source
        $configArray = [
            'localVatRate' => 0.20,
            'remoteIp' => "212.58.244.20", // A BBC server in the UK
            'currencyCode' => "GBP",
            'currencySymbol' => "£",
            'countryCode' => "GB",
            'briefDescription' => "Brief description of basket contents.",
            'intro' => "Optional intro HTML for page.",
            'paymentProviders' => $this->getPaymentProvidersConfig($passTemplatesAsConfig),
            'billingAddress' => $billingAddress,
            'hmrcVatApiConfig' => [
                "vatUrl" => $vatUrl,
            ],
        ];
        
        if ($passTemplatesAsConfig){
            $configArray['basketTemplate'] = file_get_contents(__DIR__."/../twigTemplates/Basket.twig"); 
            $configArray['addressTemplate'] = file_get_contents(__DIR__."/../twigTemplates/Address.twig");
        }
        
        file_put_contents( "/tmp/cnf", yaml_emit( $configArray, YAML_UTF8_ENCODING ) );

        /** @var BasketConfig $config */
        $config = BasketConfig::fromArray( $configArray );

        $config->intro = "An intro";
        $config->outro = "An outro";

        $config->validate();

        $vatRates = $this->getVatRates();

        /** @var \PatternSeek\ECommerce\Basket $view */
        $view = new Basket(null, null, null, $this->testLogger );

        $this->successCallback = new TestSuccess();

        $lineItems = is_array( $lineItemOrArr )?$lineItemOrArr:[$lineItemOrArr];
        
        $view->updateView(
            [
                'config' => $config,
                'vatRates' => $vatRates,
                'lineItems' => $lineItems,
                'testMode' => true,
                'chargeMode' => $chargeMode,
                'transactionSuccessCallback' => $this->successCallback
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
        $lineItem->metadata = ["somekey"=>"1", "otherkey"=>"2"];
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
        $lineItem->metadata = ["somekey"=>"1", "otherkey"=>"2"];
        return $lineItem;
    }

    /**
     * @return array
     */
    private function getPaymentProvidersConfig($passTemplatesAsConfig = false)
    {
        $ret = [
            'Stripe' => [
                'name' => 'stripe',
                'componentClass' => Stripe::class,
                'conf' => [
                    'testApiPubKey' => 'pk_test_abc123',
                    'testApiPrivKey' => 'sk_test_abc123',
                    'liveApiPubKey' => 'pk_live_abc123',
                    'liveApiPrivKey' => 'sk_live_abc123',
                    'currency' => 'GBP',
                ]
            ]
        ];
        
        if( $passTemplatesAsConfig ){
            $ret['Stripe']['template'] = file_get_contents(__DIR__."/../twigTemplates/Stripe.twig");
        }
        
        return $ret;
    }
}