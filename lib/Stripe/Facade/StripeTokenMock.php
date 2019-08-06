<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\Stripe\Facade;

use Stripe\Token;

/**
 * Class Stripe_TokenMock
 * @package PatternSeek\ECommerce\Stripe\Facade
 */
class StripeTokenMock extends Token
{

    /**
     * @var
     */
    public static $typeSetting;
    /**
     * @var
     */
    public static $cardCountrySetting;
    /**
     * @var
     */
    public static $bankCountrySetting;

    /**
     * @var
     */
    public $type;
    /**
     * @var
     */
    public $card;
    /**
     * @var
     */
    public $bank_account;

    /**
     * @param string $stripeToken
     * @param array|null|string $opts
     * @return StripeTokenMock
     */
    public static function retrieve( $stripeToken, $opts = null )
    {
        $tok = new StripeTokenMock();
        $tok->type = self::$typeSetting;
        $tok->card = self::$cardCountrySetting;
        $tok->bank_account = self::$bankCountrySetting;
        return $tok;
    }
}
