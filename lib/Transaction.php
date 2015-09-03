<?php

namespace PatternSeek\ECommerce;

use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AddressState
 * @package PatternSeek\ECommerce
 */
class Transaction extends StructClass
{

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
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $transactionDetail;

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
     * @Assert\NotBlank()
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

}