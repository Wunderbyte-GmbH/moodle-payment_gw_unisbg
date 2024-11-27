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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../config.php');

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
    public function handle_ozp_feedback($rawbodydata, $headers ) {
        ob_clean();
        $response = [
          'code' => 400,
          'info' => 'unkonwn decrypted message',
        ];

        // Handle Request.
        $cipherdata = $rawbodydata;
        $ivH = $headers['X-Initialization-Vector'];
        $zrH = $headers['X-Zahlungsdetails'];
        $ozpId = base64_decode(hex2bin($zrH));
        $iv = base64_decode(hex2bin($ivH));

        $encryptionMethod = ENCRYPTIONMETHOD;

        // Checking Incoming Data.
        if($ivH =='' || $zrH ==''){
            $data['status'] = 'error';
            $data['msg'] = 'Input arguments missing';
        }

        $decryptedMessage = openssl_decrypt(
            base64_decode($cipherdata),
            $encryptionMethod,
            AESKEY ,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($decryptedMessage !== false){
            try {
                $txResponse = json_decode($decryptedMessage);
                $data['title'] = 'OZP API';
                $data['msg'] = 'Handshake completed! PLUS Payment Service Transaction finished.';
                $data['txn'] = $txResponse->transactionID;
                $data['status'] = $txResponse->result;
                $data['ozpId'] = $ozpId;
                $data['cipher'] = $cipherdata;
                $data['ivH'] = $ivH;
                $data['zrH'] = $zrH;
                $data['iv'] = $iv;
                $data['encryptionMethod'] = $encryptionMethod;
                $response['info'] = $data;
                $response['code'] = 200;
                return $response;
            }
            catch (\Exception $e)
            {
                $response['info'] = $e->getMessage();
                return $response;
            }
        }
        return $response;
    }

    /**
      * Return success feedback
      * @param array $data
      * @return \GuzzleHttp\Psr7\Response
      */
      public function return_success($data)
      {
          return new \GuzzleHttp\Psr7\Response(
              200,
              ['Content-Type' => 'application/json'],
              $data
          );
      }

      /**
      * Return error feedback
      * @param string $e
      * @return \GuzzleHttp\Psr7\Response
      */
      public function return_error($e)
      {
          return new \GuzzleHttp\Psr7\Response(
            400,
            ['Content-Type' => 'application/json'],
            $e
        );
      }

      /**
      * Return success feedback
      * @param string $tid
      * @return object
      */
      public function get_completedtransation($tid)
      {
          global $DB;
          $select = "SELECT openorders.itemid, openorders.tid, openorders.userid, history.componentname, history.area";
          $from = "FROM {paygw_unisbg_openorders} openorders";
          $join = "INNER JOIN {local_shopping_cart_history} history ON history.identifier = openorders.id";
          $where = "WHERE openorders.tid = :tid ORDER BY history.id ASC LIMIT 1";
          $params = ['tid' => $tid];
          return $DB->get_record_sql($select . $from . $join . $where, $params);
      }
 }