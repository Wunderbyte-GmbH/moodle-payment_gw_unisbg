<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'paygw_unisbg', language 'en'
 *
 * @package    paygw_unisbg
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['modulename'] = 'Payment gateway for Uni Salzburg';

$string['cachedef_cacheaccesstoken'] = 'Cache access token';

$string['amountmismatch'] = 'The amount you attempted to pay does not match the required fee. Your account has not been debited.';
$string['authorising'] = 'Authorising the payment. Please wait...';
$string['brandname'] = 'Brand name';
$string['brandname_help'] = 'An optional label that overrides the business name for the unisbg account on the unisbg site.';
$string['cannotfetchorderdatails'] = 'Could not fetch payment details from unisbg. Your account has not been debited.';
$string['clientid'] = 'Client ID';
$string['clientid_help'] = 'The client ID that unisbg generated for your application.';
$string['environment'] = 'Environment';
$string['environment_help'] = 'You can set this to Sandbox if you are using sandbox accounts (for testing purpose only).';
$string['gatewaydescription'] = 'unisbg is an authorised payment gateway provider for processing credit card transactions.';
$string['gatewayname'] = 'unisbg';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['live'] = 'Live';
$string['paymentnotcleared'] = 'Payment not cleared by unisbg. Please try again later.';
$string['pluginname'] = 'unisbg';
$string['pluginname_desc'] = 'The unisbg plugin allows you to receive payments via unisbg.';
$string['privacy:metadata'] = 'The unisbg plugin does not store any personal data.';
$string['repeatedorder'] = 'This order has already been processed earlier.';
$string['sandbox'] = 'Sandbox';
$string['secret'] = 'Secret';
$string['secret_help'] = 'The secret that unisbg generated for your application.';

$string['checkout'] = 'Checkout';
$string['loading'] = 'Loading...';

$string['payment_added'] = 'Payment transaction was started. (Open order was added.)';
$string['payment_completed'] = 'Payment transaction was completed.';
$string['payment_successful'] = 'Payment successful. Click to continue to your course.';
$string['payment_error'] = 'An error occured during the payment. Please try again later.';
$string['delivery_error'] = 'Your payment was successful, but there was an error during delivery. Please contact support.';

$string['success'] = 'Success';
$string['error'] = 'Error';
$string['proceed'] = 'Proceed';

$string['paymentoptions'] = "Payment options";
$string['more'] = "More";

$string['quick_checkout'] = "Quick checkout";
$string['paycredit'] = "Pay with creditcard";

$string['unknownbrand'] = "UK";
$string['MASTERCARD'] = "MC";
$string['VISA'] = "VC";
$string['EPS'] = "EP";

$string['unknowncity'] = "unknown city";
$string['unknowncountry'] = "unknown country";
$string['unknownzip'] = "unknown zip";
$string['unknownaddress'] = "unknown address";

$string['tokenforverification'] = "Token for verification";
$string['successmessagepaymentinit'] = "Successfully initialised the payment process. Your purchase should be available to you soon.";
$string['errormessagepaymentinit'] = "Payment initialization was cancelled.";
$string['backtocourseoverview'] = "Back to course overview";
