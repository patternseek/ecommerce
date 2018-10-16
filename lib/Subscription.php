<?php

namespace PatternSeek\ECommerce;

use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Subscription
 * @package PatternSeek\ECommerce
 */
class Subscription extends StructClass
{

    /**
     * @var string
     * @Assert\Type(type="string")
     */
    public $validationError;
    
    /**
     * Data returned by the payment provider required for managing the subscription, JSON string
     * 
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $providerSpecificSubscriptionDataRaw;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="string")
     */
    public $providerClass;

    /**
     * Get subscription data as array
     *
     * @return array
     */
    public function getProviderSpecificSubscriptionData()
    {
        return json_decode( $this->providerSpecificSubscriptionDataRaw, true );
    }
    
    /**
     * Set subscription data as array
     * @param array $providerSpecificSubscriptionData
     */
    public function setProviderSpecificSubscriptionData( array $providerSpecificSubscriptionData )
    {
        $this->providerSpecificSubscriptionDataRaw = json_encode( $providerSpecificSubscriptionData, JSON_PRETTY_PRINT );
    }
    
}

