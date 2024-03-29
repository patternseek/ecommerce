<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 - 2021 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce;

use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Transaction
 * @package PatternSeek\ECommerce
 */
class Transaction extends StructClass
{

    /**
     * @var string
     * @Assert\Type(type="string")
     */
    public $uid;
    
    /**
     * @var boolean
     * @Assert\Type(type="boolean")
     */
    public $complete;
    
    /**
     * @var string
     * @Assert\Type(type="string")
     */
    public $validationError;

    /**
     * Whether the transaction is a live or test transaction.
     *
     * @var boolean
     * @Assert\Type(type="boolean")
     */
    public $testMode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $clientName;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $billingAddress;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $clientEmail;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $transactionDescription;

    /**
     * New transaction detail format, JSON string
     * 
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $transactionDetailRaw;

    /**
     * Old format transaction detail, CSV format
     *
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $transactionDetailLegacy;
    
    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Choice(choices = {"valid", "invalid", "unknown", "notchecked"})
     */
    public $vatNumberStatus;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $vatNumberGiven;

    /**
     * @var double
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="double")
     */
    public $transactionAmount;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{3}$/", message="Must be three characters and upper case.")
     */
    public $transactionCurrency;
    
    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $vatNumberGivenCountryCode;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $billingAddressCountryCode;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $ipCountryCode;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $paymentCountryCode;

    /**
     * @var integer
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="integer")
     */
    public $time;

    /**
     * @var string
     *
     * The "NotBlank" assertion has been removed as it
     * breaks the DelayedOrRepeatTransaction subclass and 
     * Symfony annotations don't allow subclass overrides.
     * 
     * @Assert\Type(type="string")
     */
    public $chargeID;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     * @Assert\Choice(choices = {"card", "bank_account"})
     */
    public $paymentType;

    /**
     * @var double
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="double")
     */
    public $vatAmount;

    /**
     * @var string json
     * @Assert\Type(type="string")
     */
    public $subscriptionsRaw;

    /**
     * Get subscriptions as array
     *
     * @return mixed
     */
    public function getSubscriptions()
    {
        return json_decode( $this->subscriptionsRaw, true );
    }

    /**
     * Set subscriptions as array
     * @param array $subscriptions
     */
    public function setSubscriptions( array $subscriptions )
    {
        $this->subscriptionsRaw = json_encode( $subscriptions, JSON_PRETTY_PRINT );
    }

    /**
     * Get transaction details as array
     *
     * @return mixed
     */
    public function getTransactionDetail()
    {
        return json_decode( $this->transactionDetailRaw, true );
    }

    /**
     * Set transaction details as array - These are serialised LineItems
     * @param array $transactionDetail
     */
    public function setTransactionDetail( array $transactionDetail )
    {
        $this->transactionDetailRaw = json_encode( $transactionDetail, JSON_PRETTY_PRINT );
    }

    /**
     * Convert from old CSV style transaction details to new JSON string format (which is a JSON serialised array of LineItems).
     * @return boolean True if the transaction was upgraded, false if it was already in the new format.
     */
    public function upgradeTransactionDetail()
    {
        if (!is_array( $this->getTransactionDetail() )) {
            $lines = explode( "\n", $this->transactionDetailLegacy );
            // First line is header.
            array_shift( $lines );
            $newDetails = [ ];
            foreach ($lines as $line) {
                $parts = explode( ',', $line );
                // Bad transaction.
                if (count( $parts ) < 7) {
                    continue;
                }
                $tmp = [ ];
                // New items are generated directly from LineItems
                $tmp[ 'quantity' ] = ( trim( $parts[ 0 ] ) == '-' )?1:trim( $parts[ 0 ] );
                $tmp[ 'description' ] = trim( $parts[ 1 ] );
                $tmp[ 'netPrice' ] = trim( $parts[ 2 ] );
                $tmp[ 'vatPerItem' ] = trim( $parts[ 3 ] );
                $tmp[ 'vatTypeCharged' ] = trim( $parts[ 4 ] );
                $tmp[ 'enjoyedInLocationType' ] = trim( $parts[ 5 ] );
                $tmp[ 'productType' ] = trim( $parts[ 6 ] );
                $newDetails[] = $tmp;
            }
            $this->setTransactionDetail( $newDetails );
            return true;
        }else {
            return false;
        }
    }

}
