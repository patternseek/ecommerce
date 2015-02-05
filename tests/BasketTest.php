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

use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\BasketConfig;
use PatternSeek\ECommerce\LineItem;

/**
 * Class BasketTest
 * @package PatternSeek\ECommerce\Test
 */
class BasketTest extends \PHPUnit_Framework_TestCase
{

    function testRender()
    {

        // This would usually come from a config source
        $configArray = [
            'localVatRate' => 0.20,
            'remoteIp' => "212.58.244.20", // A BBC server in the UK
            'currencyCode' => "GBP",
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
                'countryCode' => 'es',
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

        $successCallback = function( $txnDetails ){ print_r( $txnDetails ); };

        $view->update(
            [
                'transactionSuccessCallback'=>$successCallback
            ]
        );
        // TODO: Test something

        echo $view->render()->content;

        $ser = serialize( $view );
        $uns = unserialize( $ser );

        $uns->update(
            [
                'transactionSuccessCallback'=>$successCallback
            ]
        );

        $uns->render();

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
}