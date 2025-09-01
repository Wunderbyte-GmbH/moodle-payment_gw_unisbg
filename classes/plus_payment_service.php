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
 * Add dates to option.
 *
 * @package paygw_unisbg
 * @copyright 2022 Georg Maißer <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_unisbg;

defined('MOODLE_INTERNAL') || die();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/vendor/autoload.php');
require_once(__DIR__ . '/../config.php');
use GuzzleHttp\Psr7\Response;


 /**
  * PLUS PaymentService Service
  * Connector to PLUS OZP REST API
  * Handles PLUS Online Payment
  * Contact PLUS ITServices, Robert Gassner
  * @package clusterm
  * @author Dr. Jürgen Pfusterschmied
  */
class plus_payment_service {
     /**
      * Handling OZP Request send to the OZP FeedbackUrl using e.g. Middleware
      * See OZP Documentation ITABTK-FeedbackMeldungenbzgl.Zahlungsausgang-301219-0959-380.pdf
      * @param string $rawbodydata
      * @param array $headers
      * @return array
      */
    public function handle_ozp_feedback($rawbodydata, $headers) {
        ob_clean();
        $response = [
          'code' => 400,
          'info' => 'unkonwn decrypted message',
        ];

        // Handle Request.
        $cipherdata = $rawbodydata;
        $ivh = $headers['X-Initialization-Vector'];
        $zrh = $headers['X-Zahlungsdetails'];
        $ozpid = base64_decode(hex2bin($zrh));
        $iv = base64_decode(hex2bin($ivh));

        $encryptionmethod = ENCRYPTIONMETHOD;

        // Checking Incoming Data.
        if ($ivh == '' || $zrh == '') {
            $data['status'] = 'error';
            $data['msg'] = 'Input arguments missing';
        }

        $decryptedmessage = openssl_decrypt(
            base64_decode($cipherdata),
            $encryptionmethod,
            AESKEY,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($decryptedmessage !== false) {
            $txeesponse = json_decode($decryptedmessage);
            $data['title'] = 'OZP API';
            $data['msg'] = 'Handshake completed! PLUS Payment Service Transaction finished.';
            $data['txn'] = $txeesponse->transactionID;
            $data['status'] = $txeesponse->result;
            $data['ozpId'] = $ozpid;
            $data['cipher'] = $cipherdata;
            $data['ivH'] = $ivh;
            $data['zrH'] = $zrh;
            $data['iv'] = $iv;
            $data['encryptionMethod'] = $encryptionmethod;
            $response['info'] = $data;
            $response['code'] = 200;
            return $response;
        }
        return $response;
    }

    /**
     * Return success feedback
     * @param array $data
     * @return \GuzzleHttp\Psr7\Response
     */
    public function return_success_response($data) {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }

    /**
     * Return error feedback
     * @param string $e
     * @return \GuzzleHttp\Psr7\Response
     */
    public function return_error_response($e) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            $e
        );
    }

    /**
     * Returns completedtransaction
     * @param string $tid
     * @return object
     */
    public function get_completed_transaction($tid) {
        global $DB;
        $sql = "
            SELECT
                openorders.itemid,
                openorders.tid,
                openorders.userid,
                'local_shopping_cart' as component,
                '' as paymentarea
            FROM
                {paygw_unisbg_openorders} openorders
            WHERE
                openorders.tid = :tid
        ";
        $params = ['tid' => $tid];
        return $DB->get_record_sql($sql, $params);
    }
}
