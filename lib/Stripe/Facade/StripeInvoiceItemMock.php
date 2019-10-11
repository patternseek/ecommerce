<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2019 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\Stripe\Facade;

use Stripe\InvoiceItem;

class StripeInvoiceItemMock extends InvoiceItem
{

    public static $params;
    
    public $status = "succeeded";
    
    // Silences notices in tests
    public $payment_method = null;

    public static function create( $params = null, $options = null )
    {
        self::$params = $params;
        $InvoiceItem = new StripeInvoiceItemMock( "TestStripeID" );
        return $InvoiceItem;
    }

    public function confirm($params = NULL, $options = NULL){
        return true;
    }
    
    /**
     * @param string $stripeToken
     * @param array|null|string $opts
     * @return StripeInvoiceItemMock
     */
    public static function retrieve( $stripeToken, $opts = null )
    {
        $pi = new StripeInvoiceItemMock( "TestStripeID" );
        return $pi;
    }
}
