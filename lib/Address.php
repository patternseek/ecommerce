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

use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\ViewState\AddressState;

/**
 * Class Address
 */
class Address extends AbstractViewComponent
{

    /**
     * @var \PatternSeek\ECommerce\Basket
     */
    protected $parent;

    /**
     * @var AddressState An object containing state elements
     */
    protected $state;

    public function isReady()
    {
        if ($this->state->mode == 'edit') {
            return false;
        }
        $isReady = true;
        foreach ($this->state->requiredFields as $field => $label) {
            if (!$this->state->$field) {
                $isReady = false;
            }
        }
        return $isReady;
    }

    public function editModeHandler( $args )
    {
        $this->state->mode = 'edit';

        $this->parent->updateState();
        return $this->parent->render();
    }

    public function setAddressHandler( $args )
    {
        $this->state->addressLine1 = $args[ 'addressLine1' ];
        $this->state->addressLine2 = $args[ 'addressLine2' ];
        $this->state->townOrCity = $args[ 'townOrCity' ];
        $this->state->stateOrRegion = $args[ 'stateOrRegion' ];
        $this->state->postCode = $args[ 'postCode' ];
        $this->state->countryCode = strtoupper( $args[ 'countryCode' ] );
        $this->state->countryString = $this->state->trans->countriesByISO[ strtoupper($args[ 'countryCode' ]) ];

        foreach ($this->state->requiredFields as $req => $label) {
            if (!$args[ $req ]) {
                $this->setFlashError( $label . $this->state->trans->is_a_required_field );

                $this->parent->updateState();
                return $this->parent->render();
            }
        }

        $this->state->mode = 'view';

        $this->parent->setAddressStatus( $this->isReady(), $this->state->countryCode, $this->state->__toString() );

        $this->parent->updateState();
        return $this->parent->render();
    }

    /**
     * @return AddressState
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        // Template can be overridden in config
        if( null !== $this->state->passedTemplate ){
            $tplTwig = $this->state->passedTemplate;
        }else{
            $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Address.twig" );
        }
        $this->template = new TwigTemplate( $this, null, $tplTwig );
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        // Done in update
    }

    protected function updateState()
    {
        $props = $this->props;

        $this->testInputs(
            [
                'state' => [ AddressState::class, null ],
                'template' => ['string', null]
            ],
            $props
        );
        // Init
        if( ! isset( $this->state ) ){
            $this->state = $props[ 'state' ];
            $this->state->countryString = $this->state->trans->countriesByISO[ strtoupper( $this->state->countryCode ) ];
            // Allow the caller to set mode, but default to 'view'
            if (null === $this->state->mode) {
                $this->state->mode = 'view';
            }    
            $this->state->passedTemplate = $props['template'];
        }
        
        // Update
        $ready = $this->isReady();
        $this->parent->setAddressStatus( $ready, $this->state->countryCode, $this->state->__toString() );
        if (!$ready) {
            $this->state->mode = 'edit';
        }
    }

}
