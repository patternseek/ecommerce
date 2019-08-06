<?php

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
use PatternSeek\ECommerce\BasketConfig;
use PatternSeek\ECommerce\BasketTranslations;
use PatternSeek\ECommerce\TransactionSuccessCallback;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class BasketState
 * @package PatternSeek\ECommerce
 */
class BasketState extends ViewState
{

    /**
     * @var string
     */
    public $successMessage;
    
    /**
     * @var bool
     */
    public $initialised = false;
    
    /**
     * @var BasketConfig
     *
     * @Assert\Type(type="PatternSeek\ECommerce\BasketConfig")
     */
    public $config;
    
    /**
     * Translated strings. 
     * Strings for payment providers are within their PaymentProviderConfigs.
     * 
     * @var BasketTranslations
     *
     * @Assert\Type(type="PatternSeek\ECommerce\BasketTranslations")
     */
    public $trans;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $testMode;

    /**
     * @var string
     *
     * @Assert\Choice(choices = {"immediate", "delayed", "subscription"})
     */
    public $chargeMode;

    /**
     * @var array
     *
     * @Assert\Type(type="array")
     */
    public $vatRates;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $intro;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $outro;

    /**
     * @var \PatternSeek\ECommerce\LineItem[]
     *
     * @Assert\Type(type="array")
     */
    public $lineItems;

    /**
     * @var double
     *
     * @Assert\Type(type="double")
     */
    public $total;

    /**
     * @var double
     *
     * @Assert\Type(type="double")
     */
    public $vatTotal;

    /**
     * Set to true if one or more LineItems require proof of the user's VAT location
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $requireUserLocationProof;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $addressCountryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $ipCountryCode;

    /**
     * v * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $cardCountryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
     */
    public $provisionalUserCountryCode;


    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $vatNumber;

    /**
     * @var string
     *
     * @Assert\Choice(choices = {"valid", "invalid", "unknown", "notchecked"})
     */
    public $vatNumberStatus = "notchecked";

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Regex(pattern="/^[A-Z]{2}$/", message="Must be two characters and upper case.")
     */
    public $vatNumberCountryCode;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $complete = false;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $addressReady;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $addressAsString;

    /**
     * The address sub-component's rendered output.
     * @var string
     */
    public $renderedBillingAddress;

    /**
     * @var TransactionSuccessCallback
     */
    public $transactionSuccessCallback;

    /**
     * @var TransactionSuccessCallback
     */
    public $delayedTransactionSuccessCallback;

    /**
     * Determine whether payment process is ready to begin.
     * @return bool
     */
    public function readyForPaymentInfo()
    {
        return $this->addressReady;
    }

    /**
     * Is the state of our vat information ok to allow the transaction to complete
     * @return bool
     */
    public function vatInfoOk()
    {
        return (
            $this->vatNumber
            || ( !$this->requireUserLocationProof )
            || (
                ( $this->getConfirmedUserCountryCode() !== false )
                && ( $this->getConfirmedUserCountryCode() == $this->provisionalUserCountryCode )
            )
        );
    }

    /**
     * Get the confirmed country code. Based on IP, billing address and card country for B2C or
     * ROW B2B, VAT number country for B2B within EU
     * 
     * @return string Two letter country code, lower case
     */
    public function getConfirmedUserCountryCode()
    {

        // If we have a valid (or not checked due to VIES being down) VAT number then that is authoritative
        if ($vatCC = $this->validVatNumberCC()) {
            return $vatCC;
        }

        if (
            $this->ipCountryCode !== null
            && $this->addressCountryCode !== null
            && ( $this->ipCountryCode == $this->addressCountryCode )
        ) {
            return $this->ipCountryCode;
        }

        if (
            $this->ipCountryCode !== null
            && $this->cardCountryCode !== null
            && ( $this->ipCountryCode == $this->cardCountryCode )
        ) {
            return $this->ipCountryCode;
        }

        if (
            $this->addressCountryCode !== null
            && $this->cardCountryCode !== null
            && ( $this->addressCountryCode == $this->cardCountryCode )
        ) {
            return $this->addressCountryCode;
        }

        return false;
    }

    /**
     * @param
     * @return double
     */
    public function getVatRate( $countryCode )
    {
        $ucCountryCode = mb_strtoupper( $countryCode,
            'UTF-8' );
        if (!isset( $this->vatRates[ 'rates' ][ $ucCountryCode ] )) {
            return 0.0;
        }
        return ( (double)$this->vatRates[ 'rates' ][ $ucCountryCode ][ 'standard_rate' ] / 100 );
    }

    /**
     * Get VAT country code if valid or unchecked due to technical issues with VIES, else FALSE
     *
     * @return bool|string
     */
    private function validVatNumberCC()
    {
        if ($this->vatNumber && $this->vatNumberCountryCode && ( ( $this->vatNumberStatus == "valid" ) || ( $this->vatNumberStatus == "unknown" ) )) {
            return $this->vatNumberCountryCode;
        }else {
            return false;
        }
    }

}
