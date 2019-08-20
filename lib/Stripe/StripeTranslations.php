<?php
/**
 *
 * © 2019 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce\Stripe;

use PatternSeek\StructClass\StructClass;

class StripeTranslations extends StructClass
{

    public $fill_all_fields = "The basket is not ready yet. Please ensure you've filled in all required fields.";
    public $not_enough_vat_info = "Sorry but we can't collect enough information about your location to comply with EU VAT legislation with the information we have available. You have not been charged. Please contact us to arrange a manual payment.";
    public $stripe_error = "Sorry but there was a problem authorising your transaction. The payment provider said: ";
    public $please_wait = "Please wait...";
    
    public $credit_or_debit_card = "Credit or debit card";
    public $card_details = "Card details";
    public $card_number = "Card number";
    public $expiry_date = "Expiry date";
    public $cvc_number = "CVC number";
    public $submit_payment = "Submit Payment";
    
}