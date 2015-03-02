<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PatternSeek\ECommerce;

use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\StripeFacade\StripeFacade;
use PatternSeek\ECommerce\ViewState\StripeState;

/**
 * A ViewComponent for rendering Stripe checkout within a ViewComponents
 * @typedef array \UpdateType {
 * @var string $config
 * @var string $email
 * }
 *
 */
class Stripe extends AbstractViewComponent
{

    /**
     * @var \PatternSeek\ECommerce\Basket
     */
    protected $parent;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var StripeState
     */
    protected $state;

    /**
     * HTTP accessible method
     * @param $args
     * @return array
     * @throws \Exception
     * @throws \Stripe\Error\Card
     */
    public function submitFormHandler( $args )
    {

        $stripe = new StripeFacade();

        // Is the basket ready for a transaction? Or has the transaction
        // already occurred? If not then refuse to process
        // This is just a backup as the basket won't show the stripe button
        // if it's not ready or the transaction is complete.
        if (( !$this->state->ready ) || ( $this->state->complete )) {
            $this->parent->setFlashError( "The basket is not ready yet. Please ensure you've filled in all required fields." );
            return $this->renderRoot();
        }

        $this->testInputs(
            [
                'stripeToken' => [ "string" ] // Required
            ],
            $args
        );

        $c = (object)$this->state->config;
        $stripeToken = $args[ 'stripeToken' ];
        $apiPrivKey = $this->state->testMode?$c->testApiPrivKey:$c->liveApiPrivKey;
        $stripe->setApiKey( $apiPrivKey );
        $tok = $stripe->tokenRetrieve( $stripeToken, $apiPrivKey );
        $countryCode = '';
        $type = "";
        if ($tok->type == 'card') {
            $countryCode = $tok->card->country;
            $type = "card";
        }
        if ($tok->type == 'bank_account') {
            $countryCode = $tok->bank_account->country;
            $type = "bank_account";
        }

        // Do we require VAT location proof, and if so do we have
        // enough and does it match the information used
        // to calculate the original VAT?
        if (!$this->parent->confirmValidTxnFunc( $countryCode )) {
            $this->parent->setFlashError( "Sorry but we can't collect enough information about your location to comply with EU VAT legislation with the information we have available. You have not been charged. Please contact us to arrange a manual payment." );
            return $this->parent->render();
        }

        /*
         Stripe_Token Object
        (
            [_apiKey:protected] => sk_test_EqK12xvA7RysgScYONkWMXMV
            [_values:protected] => Array
            (
                [id] => tok_15CwGRBNRx9jdbLg90ZJ8XSF
                [livemode] =>
                [created] => 1419354967
                [used] =>
                [object] => token
                [type] => card
                [card] => Stripe_Card Object
                    (
                    [_apiKey:protected] => sk_test_EqK12xvA7RysgScYONkWMXMV
                    [_values:protected] => Array
                        (
                        [id] => card_15CwGRBNRx9jdbLgXp2HmuQO
                        [object] => card
                        [last4] => 4242
                        [brand] => Visa
                        [funding] => credit
                        [exp_month] => 1
                        [exp_year] => 2016
                        [fingerprint] => knXHKmMt80BmS5g1
                        [country] => US
                        [name] => tolan@overtops.org
                        [address_line1] =>
                        [address_line2] =>
                        [address_city] =>
                        [address_state] =>
                        [address_zip] =>
                        [address_country] =>
                        [cvc_check] =>
                        [address_line1_check] =>
                        [address_zip_check] =>
                        [dynamic_last4] =>
                        [customer] =>

         */

        // Create the charge on Stripe's servers - this will charge the user's card
        try{
            $charge = $stripe->chargeCreate(
                [
                    "amount" => $this->state->amount, // amount in cents/pence etc, again
                    "currency" => $c->currency,
                    "card" => $stripeToken,
                    "description" => $this->state->description
                ]
            );
        }catch( \Stripe\Error\Card $e ){
            $this->parent->setFlashError( "Sorry but there was a problem authorising your transaction. The payment provider said: '{$e->getMessage()}'" );
            return $this->parent->render();
        }

        $ret = new Transaction();
        $ret->chargeID = $charge->id;
        $ret->paymentCountryCode = $countryCode;
        $ret->paymentType = $type;

        $this->state->complete = true;
        return $this->parent->transactionSuccess( $ret );
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        $this->state = new StripeState();
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Stripe.twig" );
        $this->template = new TwigTemplate( $this, $tplTwig );
    }

    /**
     * @param $initConfig
     * @return mixed
     *
     */
    protected function initComponent( $initConfig )
    {
        $this->testInputs(
            [
                'config' => [ "array" ],  // Required, entries should be PatternSeek\ECommerce\PaymentProviderConfig
                'buttonLabel' => [ 'string', null ],                                 // Optional, default null
                'email' => [ 'string', null ],                                       // Optional, default null
                'testMode' => [ 'boolean' ]                                        // Required
            ],
            $initConfig
        );

        $c = (object)$initConfig[ 'config' ];

        if (null !== $initConfig[ 'buttonLabel' ]) {
            $initConfig[ 'buttonLabelHTML' ] = "data-label=\"{$c->buttonLabel}\"";
        }

        $initConfig[ 'apiPubKey' ] = $initConfig[ 'testMode' ]?$c->testApiPubKey:$c->liveApiPubKey;

        if (null !== $initConfig[ 'email' ]) {
            $initConfig[ 'emailHTML' ] = "data-email=\"{$initConfig['email']}\"";
        }

        $this->state = StripeState::fromArray( $initConfig );
    }

    /**
     * Using $this->state, optionally update state, optionally create child components via addOrUpdateChild(), return template props
     * @param $props
     * @return array Template props
     */
    protected function doUpdateState( $props )
    {
        $this->testInputs(
            [
                'amount' => [ 'double' ],
                'description' => [ "string" ],
                'basketReady' => [ 'boolean' ],
                'transactionComplete' => [ 'boolean' ],
                'address' => [ 'PatternSeek\ECommerce\ViewState\AddressState' ]
            ],
            $props
        );
        $this->state->amount = $props[ 'amount' ] * 100; // Stripe wants amount in cents
        $this->state->description = $props[ 'description' ];
        $this->state->ready = $props[ 'basketReady' ];
        $this->state->complete = $props[ 'transactionComplete' ];
        $this->state->address = $props[ 'address' ];

        return (array)$this->state;
    }
}
