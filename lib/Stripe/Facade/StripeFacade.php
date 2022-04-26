<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 - 2021 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PatternSeek\ECommerce\Stripe\Facade;

use Stripe\Charge;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
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
     * @param $stripePaymentIntentId
     * @param $apiPrivKey
     * @return StripePaymentIntentMock|PaymentIntent
     */
    public function paymentIntentRetrieve( $stripePaymentIntentId )
    {
        if (self::$testMode) {
            return StripePaymentIntentMock::retrieve($stripePaymentIntentId);
        }else {
            return PaymentIntent::retrieve( $stripePaymentIntentId );
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
            return StripePaymentMethodMock::retrieve($stripePaymentMethod);
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
     * @param $stripeCustomerId
     * @param $apiPrivKey
     * @return StripeCustomerMock|Customer
     */
    public function customerRetrieve( $stripeCustomerId )
    {
        if (self::$testMode) {
            return StripeCustomerMock::retrieve($stripeCustomerId);
        }else {
            return Customer::retrieve( $stripeCustomerId );
        }
    }

    /**
     * @param $params
     * @return StripeSubscriptionMock|Subscription
     */
    public function subscriptionCreate( $params )
    {
        if (self::$testMode) {
            return StripeSubscriptionMock::create( $params );
        }else {
            return Subscription::create( $params );
        }
    }
    
    /**
     * @param $stripeSubscription
     * @param $apiPrivKey
     * @return StripeSubscriptionMock|Subscription
     */
    public function subscriptionRetrieve( $stripeSubscription )
    {
        if (self::$testMode) {
            return StripeSubscriptionMock::retrieve($stripeSubscription);
        }else {
            return Subscription::retrieve( $stripeSubscription );
        }
    }
    
    
        /**
     * @param $stripeInvoice
     * @param $apiPrivKey
     * @return StripeInvoiceMock|Invoice
     */
    public function invoiceRetrieve( $stripeInvoice )
    {
        if (self::$testMode) {
            return StripeInvoiceMock::retrieve($stripeInvoice);
        }else {
            return Invoice::retrieve( $stripeInvoice );
        }
    }
    
    /**
     * @param $params
     * @return StripeInvoiceItemMock|InvoiceItem
     */
    public function invoiceItemCreate( $params )
    {
        if (self::$testMode) {
            return StripeInvoiceItemMock::create( $params );
        }else {
            return InvoiceItem::create( $params );
        }
    }

}
