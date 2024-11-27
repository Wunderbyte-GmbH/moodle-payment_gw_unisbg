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

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/classes/plus_payment_service.php');
require_once(__DIR__ . '/classes/transaction_complete.php');

use paygw_unisbg\plus_payment_service;
use paygw_unisbg\transaction_complete;

$rawbodydata = file_get_contents('php://input');
$headers = getallheaders();
// Check if an incomming ozp feedback exists.
if (!empty($rawbodydata)) {
    try {
        $pluspaymentservice = new plus_payment_service();
        $transactioncomplete = new transaction_complete();
        // Decrypt the message.
        $responsecodeanddata = $pluspaymentservice->handle_ozp_feedback(
            $rawbodydata,
            $headers
        );
        if (
            isset($responsecodeanddata['info']['status']) &&
            $responsecodeanddata['info']['status'] == 'SUCCESS'
        ) {
              $completedtransation = $pluspaymentservice->get_completed_transation($responsecodeanddata['info']['txn']);
              // Todo: Via tid, return $itemmid & $userid from unisbg_openorderstable.
              $transactioncomplete->trigger_execution($completedtransation);
              $pluspaymentservice->return_success_responde($responsecodeanddata['info']);
        }
    } catch (Exception $e) {
        $pluspaymentservice->return_error_responde($e->getMessage());
    }
}
