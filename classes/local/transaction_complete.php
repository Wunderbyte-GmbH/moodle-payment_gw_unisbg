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

namespace paygw_unisbg\local;

use context_system;
use core_external\external_function_parameters;
use core_external\external_value;
use core_payment\helper;
use core_payment\helper as payment_helper;
use paygw_unisbg\event\delivery_error;
use paygw_unisbg\event\payment_completed;
use paygw_unisbg\event\payment_successful;
use paygw_unisbg\interfaces\interface_transaction_complete;

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
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea payment area
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param string $token
     * @param string $customer
     * @param bool $ischeckstatus
     * @param string $resourcepath
     * @param int $userid
     * @return array
     */
    public static function execute(
        string $component,
        string $paymentarea,
        int $itemid,
        string $tid,
        string $token = '0',
        string $customer = '0',
        bool $ischeckstatus = false,
        string $resourcepath = '',
        int $userid = 0
    ): array {
        global $USER, $DB;

        if (empty($component)) {
            $success = false;
            return [
                'url' => '',
                'success' => false,
                'message' => get_string('internalerror', 'paygw_unisbg') .
                    " - Component missing in transaction_complete::execute function.",
            ];
        }
        if (empty($itemid)) {
            $success = false;
            return [
                'url' => '',
                'success' => false,
                'message' => get_string('internalerror', 'paygw_unisbg') .
                    " - Itemid missing in transaction_complete::execute function.",
            ];
        }
        if (empty($tid)) {
            $success = false;
            return [
                'url' => '',
                'success' => false,
                'message' => get_string('internalerror', 'paygw_unisbg') .
                    " - TransactionID (tid) missing in transaction_complete::execute function.",
            ];
        }
        if (empty($userid)) {
            $success = false;
            return [
                'url' => '',
                'success' => false,
                'message' => get_string('internalerror', 'paygw_unisbg') .
                    " - Userid missing in transaction_complete::execute function.",
            ];
        }

        $payable = payment_helper::get_payable(
            $component,
            $paymentarea,
            (int) $itemid
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
            $component,
            $paymentarea,
            (int) $itemid
        )->__toString();

        $message = '';
        $success = true;

        $existingdata = $DB->get_record(
            'paygw_unisbg',
            ['unisbg_orderid' => $tid]
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
                $component,
                $paymentarea,
                (int) $itemid,
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

            // If the delivery was not successful, we trigger an event.
            if (
                !payment_helper::deliver_order(
                    $component,
                    $paymentarea,
                    (int) $itemid,
                    $paymentid,
                    (int) $userid
                )
            ) {
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
        } catch (\Exception $e) {
            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $success = false;
            $message = get_string('internalerror', 'paygw_unisbg');
        }

        return [
            'url' => $url ?? '',
            'success' => $success,
            'message' => $message,
        ];
    }
}
