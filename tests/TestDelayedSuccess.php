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
use PatternSeek\ECommerce\DelayedTransactionSuccessCallback;
use PatternSeek\ECommerce\Stripe;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\TransactionSuccessCallback;

class TestDelayedSuccess extends DelayedTransactionSuccessCallback
{

    /**
     * @var DelayedOrRepeatTransaction
     */
    public $delayedTxn;

    function __invoke( DelayedOrRepeatTransaction $delayedTxn, Basket $basket ){
        $this->delayedTxn = $delayedTxn;
        
        $delayedSuccessOutput = (array)$delayedTxn;
        
        #unset( $delayedSuccessOutput[ 'validationError' ] );
        // This *is* set on the delayed TXN because it's the time the customer was created
        $delayedSuccessOutput['time'] = null;
        ksort( $delayedSuccessOutput );
        
        if( $delayedTxn->providerClass == Stripe::class ){
            $creds = ["apiPrivKey"=>"dummy_api_key"];
            $finalTxn = $delayedTxn->charge( $creds );
            $finalTxnOutput = (array)$finalTxn;

            unset( $finalTxnOutput[ 'validationError' ] );
            $finalTxnOutput['time'] = null;
            ksort( $finalTxnOutput );
            
        }else{
            throw new \Exception( "Txn provider should be Stripe" );
        }

        $out = ['delayedTxn'=>$delayedSuccessOutput, 'actualTxn'=>$finalTxnOutput];
        krsort( $out );
        return new Response( "text/plain", var_export( $out, true ) );
    }
}