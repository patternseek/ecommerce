<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 - 2021 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\Stripe;

use Exception;
use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\Response;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;
use PatternSeek\ECommerce\ViewState\AddressState;
use PatternSeek\ECommerce\ViewState\StripeState;
use function GuzzleHttp\default_ca_bundle;

/**
 * A ViewComponent for rendering Stripe checkout within a ViewComponents
 *
 */
class Stripe extends AbstractViewComponent
{

    /**
     * @var \PatternSeek\ECommerce\Basket
     */
    protected ?AbstractViewComponent $parent;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var StripeState
     */
    protected \PatternSeek\ComponentView\ViewState\ViewState $state;
    
    /**
     * @var AbstractChargeStrategy
     */
    private $chargeStrategy;

    protected function getStripeFacade( ){
        $c = (object)$this->state->config;
        $apiPrivKey = $this->state->testMode?$c->testApiPrivKey:$c->liveApiPrivKey;
        $stripe = new StripeFacade( $apiPrivKey );
        return $stripe;
    }

    public function confirmPaymentHandler( $args )
    {

        $stripe = $this->getStripeFacade();
        $c = (object)$this->state->config;

        # retrieve json from POST body
        $json_str = file_get_contents( 'php://input' );
        $json_obj = json_decode( $json_str );

        $amount = $this->state->amountCents;
        $currency = $c->currency;
        $description = $this->state->description;

        $intent = null;
        try{
            if (isset( $json_obj->payment_method_id )) {

                $method = $stripe->paymentMethodRetrieve( $json_obj->payment_method_id );
                $paymentCountryCode = $method->card->country;

                // Do we require VAT location proof, and if so do we have
                // enough and does it match the information used
                // to calculate the original VAT?
                if (!$this->parent->confirmValidTxnFunc( $paymentCountryCode )) {
                    $resJson = json_encode(
                        [
                            'error' =>
                                "Sorry but we can't collect enough information about your location to comply with EU VAT legislation with the information we have available. You have not been charged. Please contact us to arrange a manual payment."
                        ]
                    );
                    return new Response( "application/json", $resJson );
                }
                
                $uid = sha1(
                    serialize(
                        // This is overkill but harmless
                        [
                            time(),
                            $json_obj->payment_method_id,
                            $amount,
                            $currency, 
                            $description,
                            $this->state->email,
                            $this->state->lineItems,                  
                        ]
                    )
                );
                
                $this->parent->preTransactionNotification( $uid );

                return $this->chargeStrategy->initialPaymentAttempt(
                    $uid,
                    $json_obj->payment_method_id,
                    $amount,
                    $currency, 
                    $description,
                    $this->state->email,
                    $stripe,
                    $this->state->lineItems,                  
                    $this->state
                );                    

            }

            
        }catch( Exception $e ){
            # Display error on client
            $resJson = json_encode( [
                'error' => $e->getMessage()
            ] );
            return new Response( "application/json", $resJson );
        }
    }



    /**
     * HTTP accessible method
     * @param $args
     * @return Response
     * @throws Exception
     */
    public function completionHandler( $args )
    {

        $this->testInputs(
            [
                'paymentIntentId' => [ "string" ] // Required
            ],
            $args
        );
        $paymentIntentId = $args['paymentIntentId'];
        
        // Is the basket ready for a transaction? Or has the transaction
        // already occurred? If not then refuse to process
        // This is just a backup as the basket won't show the stripe form
        // if it's not ready or the transaction is complete.
        if (( !$this->state->ready ) || ( $this->state->complete )) {
            $this->parent->setFlashError( $this->state->trans->fill_all_fields );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }

        $stripe = $this->getStripeFacade();
        $c = (object)$this->state->config;
        $currency = $c->currency;

        $intent = $stripe->paymentIntentRetrieve( $paymentIntentId );
        $method = $stripe->paymentMethodRetrieve( $intent->payment_method );

        // Do we require VAT location proof, and if so do we have
        // enough and does it match the information used
        // to calculate the original VAT?
        $paymentCountryCode = $method->card->country;
        if (!$this->parent->confirmValidTxnFunc( $paymentCountryCode )) {
            $this->parent->setFlashError( $this->state->trans->not_enough_vat_info );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }
        
        // Charge if not done already (because the user bounced to extended authorisation)
        if ($intent->status != 'succeeded') {
            $intent->confirm();
        }
        
        if ($intent->status == 'succeeded') {
            // The payment is complete
            $txn = $this->chargeStrategy->createTransaction( $paymentIntentId, $method, $currency, $stripe, $this->state );
    
            $this->state->complete = true;
            $ret = $this->parent->transactionSuccess( $txn );
            return $ret;
        }else {
            # Invalid status
            throw new Exception( "Invalid PaymentIntent status (2): {$intent->status}" );
        }
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        // initialised in update
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
            $mainTplTwig = $this->state->passedTemplate;
        }else{
            $mainTplTwig = file_get_contents( __DIR__ . "/../../twigTemplates/Stripe.twig" );
        }
        $jsTplTwig = file_get_contents( __DIR__ . "/../../twigTemplates/StripeJs.twig" );
        $tplTwig = $mainTplTwig.$jsTplTwig;
        $this->template = new TwigTemplate( $this, null, $tplTwig );
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateState()
    {
        $props = $this->props;

        if (! isset($this->state)) {
            $this->init( $props );
        }

        $this->testInputs(
            [
                'amount' => [ 'double' ],
                'description' => [ "string" ],
                'basketReady' => [ 'boolean' ],
                'transactionComplete' => [ 'boolean' ],
                'address' => [ AddressState::class ],
                'lineItems' => [ 'array' ],
                'email' => ['string', null ],
                'translations' => [StripeTranslations::class, new StripeTranslations() ],
                'template' => ['string', null]
            ],
            $props
        );
        $this->state->amountCents = (int)($props[ 'amount' ] * 100); // Stripe wants amount in cents
        $this->state->description = $props[ 'description' ];
        $this->state->ready = $props[ 'basketReady' ];
        $this->state->complete = $props[ 'transactionComplete' ];
        $this->state->address = $props[ 'address' ];
        $this->state->lineItems = $props[ 'lineItems' ];
        $this->state->email = $props[ 'email' ];
        $this->state->trans = $props['translations'];
        $this->state->passedTemplate = $props['template'];

        switch ( $this->state->chargeMode ){
            case "immediate":
                $this->chargeStrategy = new ImmediateChargeStrategy();
                break;
            case "subscription":
                $this->chargeStrategy = new SubscriptionChargeStrategy();
                $numSubs = 0;
                foreach( $this->state->lineItems as $lineItem ){
                    if( $lineItem->subscriptionTypeId != null ){
                        $numSubs++;
                    }
                }
                if( $numSubs > 1 ){
                    throw new Exception("Only one subscription can be added to the basket at a time.");
                }
                break;
            default:
                throw new Exception("Invalid charge mode {$this->state->chargeMode}");
        }
    }

    /**
     * @param $props
     * @throws \Exception
     */
    private function init( $props )
    {
        $this->testInputs(
            [
                'config' => [ "array" ],  // Required, entries should be PatternSeek\ECommerce\Config\PaymentProviderConfig
                'buttonLabel' => [ 'string', null ],                                 // Optional, default null
                'email' => [ 'string', null ],                                       // Optional, default null
                'testMode' => [ 'boolean' ],                                        // Required
                'chargeMode' => [ 'string' ]                                        // Required
            ],
            $props
        );

        $c = (object)$props[ 'config' ];

        if (null !== $props[ 'buttonLabel' ]) {
            $props[ 'buttonLabelHTML' ] = "data-label=\"{$c->buttonLabel}\"";
        }

        $props[ 'apiPubKey' ] = $props[ 'testMode' ]?$c->testApiPubKey:$c->liveApiPubKey;

        $this->state = StripeState::fromArray( $props, true );
    }
}
