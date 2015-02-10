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
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $requireVATLocationProof;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
     */
    public $addressCountryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
     */
    public $ipCountryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
     */
    public $cardCountryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="boolean")
     */
    public $countryCodeConfirmed;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
     */
    public $countryCodeUsedForTransaction;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $vatNumber;

    /**
     * @var string
     *
     * @Assert\Choice(choices = {"valid", "invalid", "unknown"})
     */
    public $vatNumberStatus;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min = 2, max = 2)
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
    public $ready;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $addressReady;





}