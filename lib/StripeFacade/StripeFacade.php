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
    function setApiKey( $apiKey )
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
     * @return Stripe_TokenMock|Token
     */
    function tokenRetrieve( $stripeToken, $apiPrivKey )
    {
        if (self::$testMode) {
            return StripeTokenMock::retrieve( $stripeToken, $apiPrivKey );
        }else {
            return Token::retrieve( $stripeToken, $apiPrivKey );
        }
    }

    /**
     * @param $params
     * @return Stripe_ChargeMock|Charge
     */
    function chargeCreate( $params )
    {
        if (self::$testMode) {
            return StripeChargeMock::create( $params );
        }else {
            return Charge::create( $params );
        }
    }
}
