<?php

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
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
     * @Assert\Type(type='array')
     */
    public $requiredFields;

}