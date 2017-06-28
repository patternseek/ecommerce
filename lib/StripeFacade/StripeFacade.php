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
use Stripe\Stripe as StripeAPI;
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
}
