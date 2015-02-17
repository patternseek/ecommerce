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

use Stripe\Token;

/**
 * Class Stripe_TokenMock
 * @package PatternSeek\ECommerce\StripeFacade
 */
class Stripe_TokenMock extends Token
{

    /**
     * @var
     */
    static $typeSetting;
    /**
     * @var
     */
    static $cardCountrySetting;
    /**
     * @var
     */
    static $bankCountrySetting;

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
     * @return Stripe_TokenMock
     */
    static function retrieve( $stripeToken, $opts = null )
    {
        $tok = new Stripe_TokenMock();
        $tok->type = self::$typeSetting;
        $tok->card = self::$cardCountrySetting;
        $tok->bank_account = self::$bankCountrySetting;
        return $tok;
    }
}