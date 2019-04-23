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

use Stripe\Subscription;

class StripeSubscriptionMock extends Subscription
{

    public static $params;

    public static function create( $params = null, $options = null )
    {
        self::$params = $params;
        $subscription = new StripeSubscriptionMock("TestStripeSubscriptionID");
        return $subscription;
    }
}
