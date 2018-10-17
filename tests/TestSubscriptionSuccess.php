<?php
/**
 *
 * © 2015 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce\Test;

use PatternSeek\ComponentView\Response;
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\DelayedOrRepeatTransaction;
use PatternSeek\ECommerce\Stripe;
use PatternSeek\ECommerce\SubscriptionSuccessCallback;
use PatternSeek\ECommerce\Transaction;

class TestSubscriptionSuccess extends SubscriptionSuccessCallback
{

    /**
     * @var DelayedOrRepeatTransaction
     */
    public $delayedTxn;

    /**
     * @param Transaction $txn
     * @param Basket $basket
     * @return mixed
     * @throws \Exception
     */
    function __invoke( Transaction $txn, Basket $basket ){
        
        $out = (array)$txn;
        krsort($out);
        return new Response( "text/plain", var_export( $out, true ) );
    }
}