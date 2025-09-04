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
require_once(__DIR__ . '/classes/local/transaction_complete.php');

use GuzzleHttp\Psr7\Response;
use paygw_unisbg\plus_payment_service;
use paygw_unisbg\local\transaction_complete;

/**
 * Emit a proper HTTP response.
 *
 * @param int $statuscode
 * @param array $headers
 * @param mixed $data
 * @return void
 */
function emit_response(Response $response): void {
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    echo (string)$response->getBody();
    exit;
}

/**
 * Log debug information to the PHP error log with a prefix.
 *
 * @param string $message
 * @param mixed $data
 * @return void
 */
function log_debug(string $message, $data = null): void {
    if (!debugging('', DEBUG_DEVELOPER)) {
        return;
    }
    $prefix = '[paygw_unisbg] ';
    if ($data !== null) {
        $message .= ' ' . var_export($data, true);
    }
    error_log($prefix . $message);
}


$rawbodydata = file_get_contents('php://input');
$headers = getallheaders();

log_debug('Incoming request body:', $rawbodydata);
log_debug('Incoming headers:', $headers);

// Check if an incomming ozp feedback exists.
$pluspaymentservice = new plus_payment_service();
if (!empty($rawbodydata)) {
    try {
        // Decrypt the message.
        $responsecodeanddata = $pluspaymentservice->handle_ozp_feedback(
            $rawbodydata,
            $headers
        );
        log_debug('Decrypted response data:', $responsecodeanddata);
        if (
            isset($responsecodeanddata['info']['status']) &&
            $responsecodeanddata['info']['status'] == 'SUCCESS'
        ) {
            $completedtransaction = $pluspaymentservice->get_completed_transaction($responsecodeanddata['info']['txn']);
            log_debug('Status is SUCCESS, looking up transaction:', $responsecodeanddata['info']['txn']);

            if ($completedtransaction) {
                log_debug('Completed transaction object:', $completedtransaction);
                $result = transaction_complete::execute(
                    $completedtransaction->component,
                    $completedtransaction->paymentarea,
                    (int)$completedtransaction->itemid,
                    $completedtransaction->tid ?? '',
                    $completedtransaction->token ?? '0',
                    $completedtransaction->customer ?? '0',
                    $completedtransaction->ischeckstatus ?? false,
                    $completedtransaction->resourcepath ?? '',
                    $completedtransaction->userid ?? 0
                );
                log_debug('Result of transaction_complete::execute:', $result);
                $response = $pluspaymentservice->return_success_response($responsecodeanddata['info']);
                emit_response($response);
            } else {
                log_debug('No completed transaction found for txn.', $responsecodeanddata['info']['txn']);
            }
        } else {
            log_debug('Status is not SUCCESS or missing.');
        }
    } catch (Exception $e) {
        log_debug('Caught exception:', $e->getMessage());
        $response = $pluspaymentservice->return_error_response($e->getMessage());
        emit_response($response);
    }
    log_debug('Reached end of request handler, sending error response.');
    emit_response($pluspaymentservice->return_error_response('There is a problem with the data provided'));
} else {
    log_debug('Empty request body received.');
    emit_response($pluspaymentservice->return_error_response('Empty request body'));
}
