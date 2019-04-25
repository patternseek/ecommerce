<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PatternSeek\ECommerce\StripeFacade;

use Stripe\Charge;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe as StripeAPI;
use Stripe\Subscription;
use Stripe\Token;

/**
 * Wraps static Stripe/Stripe methods to allow testing
 *
 * Class StripeFacade
 * @package PatternSeek\ECommerce
 */
class StripeFacade
{

    /**
     * @var bool
     */
    public static $testMode = false;
    
    
    public function __construct( $apiKey )
    {
        if( ! self::$testMode ){
            $this->setApiVersion( "2019-03-14" );
            $this->setApiKey( $apiKey );
        }
    }

    /**
     * @param $apiKey
     */
    public function setApiKey( $apiKey )
    {
        if (self::$testMode) {
            StripeMock::setApiKey( $apiKey );
        }else {
            StripeAPI::setApiKey( $apiKey );
        }
    }
    
    public function setApiVersion( $version ){
        if (! self::$testMode) {
            \Stripe\Stripe::setApiVersion( $version );
        }
    }

    /**
     * @param $stripeToken
     * @param $apiPrivKey
     * @return StripeTokenMock|Token
     */
    public function tokenRetrieve( $stripeToken, $apiPrivKey )
    {
        if (self::$testMode) {
            return StripeTokenMock::retrieve( $stripeToken, $apiPrivKey );
        }else {
            return Token::retrieve( $stripeToken, $apiPrivKey );
        }
    }
    
        /**
     * @param $stripePaymentIntent
     * @param $apiPrivKey
     * @return StripePaymentIntentMock|PaymentIntent
     */
    public function paymentIntentRetrieve( $stripePaymentIntent )
    {
        if (self::$testMode) {
            return StripePaymentIntentMock::create($stripePaymentIntent);
        }else {
            return PaymentIntent::retrieve( $stripePaymentIntent );
        }
    }
    
    /**
     * @param $stripePaymentMethod
     * @param $apiPrivKey
     * @return StripePaymentMethodMock|PaymentMethod
     */
    public function paymentMethodRetrieve( $stripePaymentMethod )
    {
        if (self::$testMode) {
            return StripePaymentMethodMock::create($stripePaymentMethod);
        }else {
            return PaymentMethod::retrieve( $stripePaymentMethod );
        }
    }
    
    /**
     * @param $params
     * @return StripeChargeMock|Charge
     */
    public function chargeCreate( $params )
    {
        if (self::$testMode) {
            return StripeChargeMock::create( $params );
        }else {
            return Charge::create( $params );
        }
    }

    /**
     * @param $params
     * @return StripePaymentIntentMock|PaymentIntent
     */
    public function paymentIntentCreate( $params )
    {
        if (self::$testMode) {
            return StripePaymentIntentMock::create( $params );
        }else {
            return PaymentIntent::create( $params );
        }
    }
    
    /**
     * @param $params
     * @return StripeCustomerMock|Customer
     */
    public function customerCreate( $params )
    {
        if (self::$testMode) {
            return StripeCustomerMock::create( $params );
        }else {
            return Customer::create( $params );
        }
    }

    /**
     * @param $params
     * @return StripeCustomerMock|Customer
     */
    public function subscriptionCreate( $params )
    {
        if (self::$testMode) {
            return StripeSubscriptionMock::create( $params );
        }else {
            return Subscription::create( $params );
        }
    }
}
