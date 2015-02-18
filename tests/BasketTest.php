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
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\BasketConfig;
use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\StripeFacade\Stripe_TokenMock;
use PatternSeek\ECommerce\StripeFacade\StripeFacade;

/**
 * Class BasketTest
 * @package PatternSeek\ECommerce\Test
 */
class BasketTest extends \PHPUnit_Framework_TestCase
{

    protected $successCallback;

    function testRender()
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
            'billingAddress' => [
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
            ]
        ];
        file_put_contents( "/tmp/cnf", yaml_emit( $configArray, YAML_UTF8_ENCODING ) );

        /** @var BasketConfig $config */
        $config = BasketConfig::fromArray( $configArray );

        $config->intro = "An intro";
        $config->outro = "An outro";

        $config->validate();

        $vatRates = $this->getVatRates();

        $lineItem = new LineItem();
        $lineItem->description = "Some event ticket";
        $lineItem->netPrice = 100;
        $lineItem->quantity = null;
        $lineItem->vatJurisdictionType = "remote";

        $initConfig = [
            'config' => $config,
            'vatRates' => $vatRates,
            'lineItems' => [ $lineItem ],
            'testMode' => true
        ];

        /** @var \PatternSeek\ECommerce\Basket $view */
        $view = new Basket( null, null, $initConfig );

        $successOutput = [ ];
        $this->successCallback =
            function ( $txnDetails ) use ( &$successOutput ){
                $successOutput = $txnDetails;
                return new ViewComponentResponse( "text/plain", ">>>Sample success page<<<" );
            };

        $view->updateProps(
            [
                'transactionSuccessCallback' => $this->successCallback
            ]
        );

        $view->render();

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

        $ser = serialize( $view );
        /** @var Basket $uns */
        $uns = unserialize( $ser );

        $uns->updateProps(
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
        $uns = unserialize( $ser );
        $this->failOn3DifferentCountries( $uns );
        /** @var Basket $uns */
        $uns = unserialize( $ser );
        $this->failOnOnlyIPandCardMatch( $uns );
        /** @var Basket $uns */
        $uns = unserialize( $ser );
        $this->succeedOnSameAddressAndCardCountries( $uns, $successOutput );
        /** @var Basket $uns */
        $uns = unserialize( $ser );
        $this->succeedOnAllCountriesMatch( $uns, $successOutput );

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
     * @param $uns
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
     * @param $uns
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
        $this->assertTrue(
            $execOut == ">>>Sample success page<<<"
        );

        $expected = [
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

        $this->assertTrue(
            $successOutput == $expected
        );
    }

    /**
     * @param $uns
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

        $this->assertTrue(
            $execOut == ">>>Sample success page<<<"
        );

        $expected = [
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
            'time' => $successOutput[ 'time' ]
        ];

        $this->assertTrue( $successOutput == $expected );
    }
}