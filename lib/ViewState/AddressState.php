<?php

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
use PatternSeek\ECommerce\BasketTranslations;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AddressState
 * @package PatternSeek\ECommerce
 */
class AddressState extends ViewState
{

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $addressLine1;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $addressLine2;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $townOrCity;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $stateOrRegion;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $postCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $countryString;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min="2", max="2")
     */
    public $countryCode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Choice(choices = {"edit", "view"})
     */
    public $mode = 'view';

    /**
     * @var string[]
     *
     * @Assert\Type(type="array")
     */
    public $requiredFields;
    
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
     * An optional Twig template to override the default
     * 
     * @var string
     */
    public $passedTemplate;

    /**
     * @return string
     */
    public function __toString()
    {
        return implode( "\n",
            [
                $this->addressLine1,
                $this->addressLine2,
                $this->townOrCity,
                $this->stateOrRegion,
                $this->postCode,
                $this->countryString
            ]
        );
    }
}
