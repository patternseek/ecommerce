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

use PatternSeek\ComponentView\ViewComponentResponse;
use PatternSeek\DependencyInjector\DependencyInjector;
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\BasketConfig;
use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\StripeFacade\Stripe_TokenMock;
use PatternSeek\ECommerce\StripeFacade\StripeFacade;
use PatternSeek\ECommerce\Transaction;
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

    public function testElectronicServiceToUKConsumer()
    {
        $billingAddress = $this->getUKAddress();
        $lineItem = $this->getElectronicServiceLineItem();
        $successOutput = [ ];
        /** @var Basket $view */
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireVATLocationProof
        );
        $this->assertTrue(
            $state->getConfirmedCountryCode() == "GB" // UK address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireVATLocationProof // Not needed for normal services
        );
        $this->assertTrue(
            $state->getConfirmedCountryCode() == "GB" // UK address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireVATLocationProof
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode() // ES address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireVATLocationProof // Not needed for normal services
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode() // ES address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireVATLocationProof
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode()
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireVATLocationProof // Not needed for normal services
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode()
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => "333289454" ] ); //BBC VAT number

        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireVATLocationProof
        );
        $this->assertTrue(
            $state->getConfirmedCountryCode() == "GB" // UK address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );

        $view->render( "validateVatNumber", [ "countryCode" => "GB", "vatNumber" => "333289454" ] ); //BBC VAT number

        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireVATLocationProof // Not needed for normal services
        );
        $this->assertTrue(
            $state->getConfirmedCountryCode() == "GB" // UK address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );

        $view->render( "validateVatNumber",
            [ "countryCode" => "ES", "vatNumber" => "A28015865" ] ); //Telefonica Spain VAT number

        $state = $view->getStateForTesting();
        $this->assertTrue(
            $state->requireVATLocationProof
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode() // ES address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );

        $view->render( "validateVatNumber",
            [ "countryCode" => "ES", "vatNumber" => "A28015865" ] ); //Telefonica Spain VAT number

        $state = $view->getStateForTesting();
        $this->assertFalse(
            $state->requireVATLocationProof // Not needed for normal services
        );
        $this->assertFalse(
            $state->getConfirmedCountryCode() // ES address, UK IP
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
        $view = $this->prepareBasket( $lineItem, $successOutput, $billingAddress );
        $state = $view->getStateForTesting();

        $serialised = serialize( $view );
        /** @var Basket $unserialised */
        $unserialised = unserialize( $serialised );

        $unserialised->updateProps(
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
        if (!( $ratesStr = @file_get_contents( "/tmp/vatrates.json" ) )) {
            $ratesStr = file_get_contents( "https://euvatrates.com/rates.json" );
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
        Stripe_TokenMock::$typeSetting = 'card';
        Stripe_TokenMock::$cardCountrySetting = (object)[ 'country' => 'ES' ];

        $uns->updateProps(
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
        Stripe_TokenMock::$typeSetting = 'card';
        Stripe_TokenMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
        $uns->updateProps(
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
     * @param $successOutput
     * @throws \Exception
     */
    protected function succeedOnSameAddressAndCardCountries( $uns, &$successOutput )
    {
        // US card + US address + GB IP, should succeed
        Stripe_TokenMock::$typeSetting = 'card';
        Stripe_TokenMock::$cardCountrySetting = (object)[ 'country' => 'US' ];
        $uns->updateProps(
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
            'transactionDetail' => "Quantity, Description, Net per item, VAT per item, VAT type\n-, Some online service, 100, 0, zero\n",
            'chargeID' => 'TestStripeID',
            'paymentCountryCode' => 'US',
            'paymentType' => 'card',
            'vatNumberStatus' => 'notchecked',
            'vatNumberGiven' => null,
            'vatNumberGivenCountryCode' => null,
            'transactionAmount' => 100,
            'billingAddressCountryCode' => 'US',
            'ipCountryCode' => 'GB',
            'vatCalculationBaseOnCountryCode' => 'US',
            'vatRateUsed' => 0,
            'time' => $successOutput[ 'time' ]
        ];
        ksort( $expected );
        $this->assertEquals( var_export( $expected, true ), $execOut  );

    }

    /**
     * @param Basket $uns
     * @param $successOutput
     */
    protected function succeedOnAllCountriesMatch( $uns, &$successOutput )
    {
        // GB card + GB address + GB IP, should succeed
        Stripe_TokenMock::$typeSetting = 'card';
        Stripe_TokenMock::$cardCountrySetting = (object)[ 'country' => 'GB' ];
        $uns->updateProps(
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

        $uns->updateProps(
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
            'transactionDetail' => "Quantity, Description, Net per item, VAT per item, VAT type\n-, Some online service, 100, 20, customer\n",
            'chargeID' => 'TestStripeID',
            'paymentCountryCode' => 'GB',
            'paymentType' => 'card',
            'vatNumberStatus' => 'notchecked',
            'vatNumberGiven' => null,
            'vatNumberGivenCountryCode' => null,
            'transactionAmount' => 120,
            'billingAddressCountryCode' => 'GB',
            'ipCountryCode' => 'GB',
            'vatCalculationBaseOnCountryCode' => 'GB',
            'vatRateUsed' => 0.20000000000000001,
            'time' => null
        ];
        ksort( $expected );
        $this->assertEquals( var_export( $expected, true ), $execOut  );

    }

    /**
     * @param LineItem $lineItem
     * @param $successOutput
     * @param $billingAddress
     * @return array
     * @throws \Exception
     */
    private function prepareBasket( LineItem $lineItem, &$successOutput, $billingAddress )
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
            'paymentProviders' => [
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
            ],
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
            

        $view->updateProps(
            [
                'config' => $config,
                'vatRates' => $vatRates,
                'lineItems' => [ $lineItem ],
                'testMode' => true,
                
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
        $lineItem->quantity = null;
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
        $lineItem->quantity = null;
        $lineItem->productType = "normalservices";
        return $lineItem;
    }
}