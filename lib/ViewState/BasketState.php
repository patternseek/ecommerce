<?php

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
use PatternSeek\ECommerce\BasketConfig;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class BasketState
 * @package PatternSeek\ECommerce
 */
class BasketState extends ViewState
{

    /**
     * @var BasketConfig
     *
     * @Assert\Type(type="PatternSeek\ECommerce\BasketConfig")
     */
    public $config;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $testMode;

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
     * Set to true if one or more LineItems require proof of the user's VAT location
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $requireVATLocationProof;

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
    public $vatCalculatedBasedOnCountryCode;


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
     * @var string[]
     *
     * @Assert\Type(type="array")
     */
    public $paymentProviderNames;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $addressReady;

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
            || ( !$this->requireVATLocationProof )
            || (
                ( $this->getConfirmedCountryCode() != false )
                && ( $this->getConfirmedCountryCode() == $this->vatCalculatedBasedOnCountryCode )
            )
        );
    }

    /**
     * Get either the confirmed country code, or failing that the address country code
     * @return string Two letter country code, lower case
     */
    public function getConfirmedCountryCode()
    {
        if (
            $this->ipCountryCode != null
            && $this->addressCountryCode != null
            && ( $this->ipCountryCode == $this->addressCountryCode )
        ) {
            return $this->ipCountryCode;
        }

        if (
            $this->ipCountryCode != null
            && $this->cardCountryCode != null
            && ( $this->ipCountryCode == $this->cardCountryCode )
        ) {
            return $this->ipCountryCode;
        }

        if (
            $this->addressCountryCode != null
            && $this->cardCountryCode != null
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

}