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
use paygw_unisbg\event\payment_successful;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Transaction complete class.
 */
class transaction_complete {
    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param object $completedtransation Name of the component that the itemid belongs to
     * @return array
     */
    public static function trigger_execution(
        object $completedtransation
    ): array {

        global $USER, $DB, $CFG, $DB;

        if (empty($completedtransation)) {
            // Purchase already stored.
            $success = false;
            $message = get_string('internalerror', 'paygw_unisbg');
        } else {
            $payable = payment_helper::get_payable(
                $completedtransation->component,
                $completedtransation->paymentarea,
                (int) $completedtransation->itemid
            );
            $currency = $payable->get_currency();

            // Add surcharge if there is any.
            $surcharge = helper::get_gateway_surcharge('unisbg');
            $amount = helper::get_rounded_cost(
                $payable->get_amount(),
                $currency,
                $surcharge
            );

            $url = helper::get_success_url(
                $completedtransation->component,
                $completedtransation->paymentarea,
                (int) $completedtransation->itemid
            )->__toString();

            $message = '';
            $success = true;

            $existingdata = $DB->get_record(
                'paygw_unisbg',
                ['unisbg_orderid' => $completedtransation->tid]
            );

            if (!empty($existingdata)) {
                return [
                    'url' => $url ?? '',
                    'success' => true,
                    'message' => 'doublechecking payment',
                ];
            }

            try {
                $paymentid = payment_helper::save_payment(
                    $payable->get_account_id(),
                    $completedtransation->component,
                    $completedtransation->paymentarea,
                    (int) $completedtransation->itemid,
                    (int) $USER->id,
                    $amount,
                    $currency,
                    'unisbg'
                );

                $record = new \stdClass();
                $record->paymentid = $paymentid;
                $record->unisbg_orderid = $completedtransation->tid;
                $record->paymentbrand = 'unknown';
                $record->pboriginal = 'unknown';

                $DB->insert_record('paygw_unisbg', $record);

                // Set status in open_orders to complete.
                if (
                    $existingrecord = $DB->get_record(
                        'paygw_unisbg_openorders',
                        ['tid' => $completedtransation->tid]
                    )
                ) {
                    $existingrecord->status = 3;
                    $DB->update_record('paygw_unisbg_openorders', $existingrecord);

                    // We trigger the payment_completed event.
                    $context = context_system::instance();
                    $event = payment_completed::create([
                        'context' => $context,
                        'userid' => $completedtransation->userid,
                        'other' => [
                            'orderid' => $completedtransation->tid,
                        ],
                    ]);
                    $event->trigger();
                }

                // We trigger the payment_successful event.
                $context = context_system::instance();
                $event = payment_successful::create(['context' => $context, 'other' => [
                    'message' => $message,
                    'orderid' => $completedtransation->tid,
                ]]);
                $event->trigger();

                // If the delivery was not successful, we trigger an event.
                if (
                    !payment_helper::deliver_order(
                        $completedtransation->component,
                        $completedtransation->paymentarea,
                        (int) $completedtransation->itemid,
                        $paymentid,
                        (int) $completedtransation->userid
                    )
                ) {
                    $context = context_system::instance();
                    $event = delivery_error::create(
                        [
                          'context' => $context, 'other' =>
                          [
                            'message' => $message,
                            'orderid' => $completedtransation->tid,
                          ],
                        ]
                    );
                    $event->trigger();
                }
            } catch (\Exception $e) {
                debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $success = false;
                $message = get_string('internalerror', 'paygw_unisbg');
            }
        }

        return [
            'url' => $url ?? '',
            'success' => $success,
            'message' => $message,
        ];
    }
}
