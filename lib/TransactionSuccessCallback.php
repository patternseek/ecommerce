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

namespace PatternSeek\ECommerce;

/**
 * Used in place of a closure as it needs to be serialised.
 * 
 * Usage:
 * 
 * class MyCallback extends TransactionSuccessCallback{
 *      public function __invoke( Transaction $txn, Basket $basket ){
 *          //.. Do something with $txn, $basket and $this->variables
 *      }
 * } 
 * 
 * $callback = new TransactionSuccessCallback( ['neededVariable'=>$neededVariable, 'otherVariable'=>$other ] );
 * Then pass to the Basket component via the 'transactionSuccessCallback' property of the updateView() method (or via addOrUpdateChild() if it's a child of another component). 
 * 
 * Note that the $variables property will have been serialised so won't maintain references to live variables.
 * If the Basket is embedded in anothe ViewComponent then fresh data can be fetched from that via $basket->getParent()
 * 
 * @package PatternSeek\ECommerce
 */
abstract class TransactionSuccessCallback
{

    protected $variables;

    public function __construct( $variables = [] ){
        $this->variables = $variables;
    }
    
    abstract public function __invoke( Transaction $txn, Basket $basket );

    /**
     * Provides information to the client about a transaction that is about to be attemped.
     * This method is called by the basket once we're ready to begin the transaction.
     * 
     * @param Transaction $txn
     */
    public function preTransactionNotification( Transaction $txn ){
        // Optional
    }
    
}
