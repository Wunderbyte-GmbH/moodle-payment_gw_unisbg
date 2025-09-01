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
 * Settings for the unisbg payment gateway
 *
 * @package    paygw_unisbg
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/classes/plus_payment_service.php');
require_once(__DIR__ . '/classes/external/transaction_complete.php');

use paygw_unisbg\plus_payment_service;
use paygw_unisbg\external\transaction_complete;

$rawbodydata = file_get_contents('php://input');
$headers = getallheaders();
// Check if an incomming ozp feedback exists.
if (!empty($rawbodydata)) {
    try {
        $pluspaymentservice = new plus_payment_service();
        // Decrypt the message.
        $responsecodeanddata = $pluspaymentservice->handle_ozp_feedback(
            $rawbodydata,
            $headers
        );
        if (
            isset($responsecodeanddata['info']['status']) &&
            $responsecodeanddata['info']['status'] == 'SUCCESS'
        ) {
            $completedtransaction = $pluspaymentservice->get_completed_transaction($responsecodeanddata['info']['txn']);
            if ($completedtransaction) {
                transaction_complete::execute(
                    $completedtransaction->component,
                    $completedtransaction->paymentarea,
                    $completedtransaction->itemid,
                    $completedtransaction->tid ?? '',
                    $completedtransaction->token ?? '0',
                    $completedtransaction->customer ?? '0',
                    $completedtransaction->ischeckstatus ?? false,
                    $completedtransaction->resourcepath ?? '',
                    $completedtransaction->userid ?? 0
                );
                $pluspaymentservice->return_success_response($responsecodeanddata['info']);
            }
        }
    } catch (Exception $e) {
        $pluspaymentservice->return_error_response($e->getMessage());
    }
}
