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
 * This class contains a list of webservice functions related to the unisbg payment gateway.
 *
 * @package    paygw_unisbg
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_unisbg;

use context_system;
use core_payment\helper;
use core_payment\helper as payment_helper;
use paygw_unisbg\event\delivery_error;
use paygw_unisbg\event\payment_completed;
use paygw_unisbg\event\payment_error;
use paygw_unisbg\event\payment_successful;
use local_shopping_cart\interfaces\interface_transaction_complete;
use paygw_unisbg\interfaces\interface_transaction_complete as ug_interface_transaction_complete;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

if (!interface_exists(interface_transaction_complete::class)) {
    class_alias(ug_interface_transaction_complete::class, interface_transaction_complete::class);
}

/**
 * Transaction complete class.
 */
class transaction_complete  {
    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea payment area
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param int $userid
     * @return array
     */
    public static function trigger_execution(
        string $component,
        string $paymentarea,
        int $itemid,
        string $tid = '',
        int $userid = 0
    ): array {

        global $USER, $DB, $CFG, $DB;

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('unisbg');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $successurl = helper::get_success_url($component, $paymentarea, $itemid)->__toString();
        $success = false;
        $message = '';
        $status = 'success';

        if ($status == 'success') {
            $url = $successurl;
            $success = true;

            // Check if order is existing.

            $checkorder = $DB->get_record('paygw_unisbg_openorders', [['tid' => $tid, 'itemid' => $itemid,
            'userid' => intval($USER->id)]]);

            $existingdata = $DB->get_record('paygw_unisbg', ['unisbg_orderid' => $tid]);

            if (!empty($existingdata)) {
                return [
                    'url' => $url ?? '',
                    'success' => true,
                    'message' => 'doublechecking payment',
                ];
            }

            if (empty($checkorder)) {
                // Purchase already stored.
                $success = false;
                $message = get_string('internalerror', 'paygw_unisbg');
            } else {
                try {
                    $paymentid = payment_helper::save_payment(
                        $payable->get_account_id(),
                        $component,
                        $paymentarea,
                        $itemid,
                        (int) $USER->id,
                        $amount,
                        $currency,
                        'unisbg'
                    );

                    $record = new \stdClass();
                    $record->paymentid = $paymentid;
                    $record->unisbg_orderid = $tid;

                    $record->paymentbrand = 'unknown';
                    $record->pboriginal = 'unknown';

                    $DB->insert_record('paygw_unisbg', $record);

                    // Set status in open_orders to complete.
                    if (
                        $existingrecord = $DB->get_record(
                            'paygw_unisbg_openorders',
                            ['tid' => $tid]
                        )
                    ) {
                        $existingrecord->status = 3;
                        $DB->update_record('paygw_unisbg_openorders', $existingrecord);

                        // We trigger the payment_completed event.
                        $context = context_system::instance();
                        $event = payment_completed::create([
                            'context' => $context,
                            'userid' => $userid,
                            'other' => [
                                'orderid' => $tid,
                            ],
                        ]);
                        $event->trigger();
                    }

                    // We trigger the payment_successful event.
                    $context = context_system::instance();
                    $event = payment_successful::create(['context' => $context, 'other' => [
                        'message' => $message,
                        'orderid' => $tid,
                    ]]);
                    $event->trigger();

                    // The order is delivered.
                    // If the delivery was not successful, we trigger an event.
                    if (!payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $userid)) {
                        $context = context_system::instance();
                        $event = delivery_error::create(
                            [
                              'context' => $context, 'other' =>
                              [
                                'message' => $message,
                                'orderid' => $tid,
                              ],
                            ]
                        );
                        $event->trigger();
                    }

                    // Delete transaction after its been delivered.
                } catch (\Exception $e) {
                    debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $success = false;
                    $message = get_string('internalerror', 'paygw_unisbg');
                }
            }
        } else if ($status == 'error') {
            // We trigger the payment_successful event.
            $context = context_system::instance();
            $event = payment_error::create(['context' => $context, 'other' => [
                'message' => $message,
                'orderid' => $tid,
                'itemid' => $itemid,
                'component' => $component,
                'paymentarea' => $paymentarea]]);
            $event->trigger();
        }

        return [
            'url' => $url ?? '',
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Trigger the webservice.
     *
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param int $userid
     * @return array
     */
    public static function trigger_transaction_completed_webservice(
        int $itemid,
        string $tid = '',
        int $userid = 0
    ): array {
        $notifyurl = new \moodle_url(
          '/webservice/rest/server.php',
          [
              'wsfunction' => 'local_shopping_cart_verify_purchase',
              'wstoken' => get_config('paygw_unisbg', 'tokenforverification'),
              'moodlewsrestformat' => 'json',
              'identifier' => $itemid,
              'tid' => $tid,
              'paymentgateway' => 'unisbg',
              'userid' => $userid,
          ]
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $notifyurl->out(false));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \moodle_exception('webservice_call_failed', 'error', '', curl_error($ch));
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            throw new \moodle_exception('webservice_call_failed', 'error', '', 'Invalid HTTP response: ' . $httpcode);
        }

        // Decode the JSON response
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('webservice_call_failed', 'error', '', 'Invalid JSON response');
        }

        return $decodedResponse;
    }
}
