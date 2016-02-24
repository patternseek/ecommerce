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

use Stripe\Stripe;

/**
 * Class StripeMock
 * @package PatternSeek\ECommerce\StripeFacade
 */
class StripeMock extends Stripe
{

    /**
     * @var
     */
    public static $apiKey;

    // Currently redundant but including for completeness in StripeFacade
    /**
     * @param string $apiKey
     */
    public static function setApiKey( $apiKey )
    {
        self::$apiKey = $apiKey;
    }
}
