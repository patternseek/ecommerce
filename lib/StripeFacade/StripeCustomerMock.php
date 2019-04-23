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

use Stripe\Customer;

class StripeCustomerMock extends Customer
{

    public static $params;

    public static function create( $params = null, $options = null )
    {
        self::$params = $params;
        $customer = new StripeCustomerMock("TestStripeCustomerID");
        return $customer;
    }
}
