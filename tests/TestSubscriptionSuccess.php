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
use PatternSeek\ECommerce\Subscription;
use PatternSeek\ECommerce\SubscriptionSuccessCallback;

class TestSubscriptionSuccess extends SubscriptionSuccessCallback
{

    /**
     * @var DelayedOrRepeatTransaction
     */
    public $delayedTxn;

    /**
     * @param Subscription[] $subs
     * @param Basket $basket
     * @return mixed
     * @throws \Exception
     */
    function __invoke( array $subs, Basket $basket ){
        
        $out = [];
        foreach ( $subs as $sub ){
            if( $sub->providerClass !== Stripe::class ){
                throw new \Exception( "Txn provider should be Stripe" );
            }
            $subOut = $sub->getProviderSpecificSubscriptionData();
            krsort($subOut);
            $out[] = $subOut;
        }
        return new Response( "text/plain", var_export( $out, true ) );
    }
}