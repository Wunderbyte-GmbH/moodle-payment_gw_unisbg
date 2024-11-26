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
 * Contains helper class to work with unisbg REST API.
 *
 * @package    paygw_unisbg
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_unisbg;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/user/lib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once($CFG->libdir . '/filelib.php');
require_once(dirname(__FILE__) . '/../config.php');

/**
 * The payment_added event.
 *
 * @package     paygw_unisbg
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Jacob Viertel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unisbg_helper {
    /**
     * @var string base URL
     */
    private $baseurl;
    /**
     * @var string ws accesstoken
     */
    private $accesstoken;

    /**
     * helper constructor
     *
     * @param string $secret unisbg secret.
     * @param string $environment Whether we are working with the sandbox environment or not.
     */
    public function __construct($environment, string $secret) {
        $this->accesstoken = $secret;
        if ($environment == 'sandbox') {
            $this->baseurl = 'https://axqual.sbg.ac.at/ords/ax_app_online_zahlung/init/starttransaction';
        } else {
            $this->baseurl = 'https://axapp.sbg.ac.at/ords/ax_app_online_zahlung/init/starttransaction';
        }
    }

    /**
     * Returns List of available prodivers for this gateway.
     * @param  array $data shoppingcart id
     * @return string
     */
    public function inittt_transaction($data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accesstoken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $responsedata = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $responsedata;
    }

    /**
     * PLUS O2P Transaction URI Builder
     * Creates a new Transaction in the O2P
     * Create a URI to the O2P Webportal to process the payment
     * Please take a look to the O2P Documentation for options and fields of $paymentData
     *
     * @param array $data
     * @return mixed
     */
    public function init_transaction($data) {
        $headers = [
            'Cache-Control: no-cache',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->accesstoken,
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contenttype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpcode === 200 && strpos($contenttype, 'application/json') === 0) {
            $o2ptransaction = json_decode($response, true);
            return $o2ptransaction;
        } else {
            echo "Failed to create transaction. HTTP Code: $httpcode";
        }
        return false;
    }



    /**
     * Checks out a cart in order to process the payment.
     *
     * @param  int $cartid Cart Id
     * @param  int $providerid I.E Creditcard, Klarna etc.
     * @param  string $redirecturl The url to which the gateway redirects after payment
     * @param  object $userdata Containing the user data
     * @param  int $itemid shoppingcart id
     * @return string The url that can be called for the redirect
     */
    public function checkout_cart($cartid, $providerid, $redirecturl, $userdata, $itemid) {

        profile_load_custom_fields($userdata);

        $notifyurl = new \moodle_url(
            '/webservice/rest/server.php',
            [
                'wsfunction' => 'local_shopping_cart_verify_purchase',
                'wstoken' => get_config('paygw_unisbg', 'tokenforverification'),
                'moodlewsrestformat' => 'json',
                'identifier' => $itemid,
                'tid' => $cartid,
                'paymentgateway' => 'unisbg',
                'userid' => $userdata->id,
            ]
        );

        $obj = (object) [
            "provider_id" => $providerid,
            "user_variable" => "localIdentifierCheckout",
            "email" => !empty($userdata->email) ? $userdata->email : 'Email Uknown',
            "gender" => 0,
            "first_name" => !empty($userdata->firstname) ? $userdata->firstname : 'First Name Unknown',
            "last_name" => !empty($userdata->lastname) ? $userdata->lastname : 'Last Name Unknown',
            "address" => !empty($userdata->address) ? $userdata->address : "-",
            "zip" => !empty($userdata->profile['postcode']) ? $userdata->profile['postcode'] :
                get_string('unknownzip', 'paygw_unisbg'),
            "city" => !empty($userdata->city) ? $userdata->city : get_string('unknowncity', 'paygw_unisbg'),
            "country" => !empty($userdata->country) ? $userdata->country : get_string('unknowncountry', 'paygw_unisbg'),
            "ip" => "8.8.8.8",
            "payment_reference" => $itemid,
            "user_url_success" => $redirecturl,
            "user_url_failure" => $redirecturl,
            "user_url_cancel" => $redirecturl,
            "user_url_pending" => $redirecturl,
            "user_url_timeout" => $redirecturl,
            "user_url_notify" => $notifyurl->out(false),
        ];
        $data = json_encode($obj);
        $headers = [
            'Content-Type: application/json',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/cart' . '/' . $cartid . '/checkout');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        $obj = json_decode($result);
        return $obj->object->url_instant;
    }

    /**
     * Checks the Payment status for a given cartid
     *
     * @param  int $cartid
     * @return object|string|null Formatted API response.
     */
    public function check_status($cartid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/cart' . '/'  . $cartid . '/checkout');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        $info = curl_getinfo($ch);
        curl_close($ch);

        if (!$response = json_decode($responsedata)) {
            return strval($responsedata);
        }
        return $response;
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @param  float $amount
     * @param  int $itemid
     * @param  array $items
     * @return array $result Unformatted API result
     */
    public function get_starttransaction_data($amount, $itemid, $items) {
        global $USER;
        $user = \core_user::get_user(10);
        $transactiondata = $this->get_redirect_urls();
        if (true) {
            $address = self::extract_address_parts($user->address);
            $transactiondata['ext_pers_id'] = $user->id;
            $transactiondata['extp_vorname'] = $user->firstname;
            $transactiondata['extp_nachname'] = $user->lastname;
            $transactiondata['extp_strasse'] = $address['name'] ?? null;
            $transactiondata['extp_hausnummer'] = $address['number'] ?? null;
            $transactiondata['extp_stadt'] = $user->city ?? null;
            $transactiondata['extp_plz'] = '5020';
            $transactiondata['extp_land_iso'] = $user->country ?? null;
            $transactiondata['extp_mail'] = $user->email ?? null;
        } else if (false) {
            $transactiondata['stud_pers_id'] = '1';
        } else {
            $transactiondata['bed_pers_id'] = '2';
        }
        $transactiondata['betrag'] = $amount;
        $transactiondata['zahlungszweck'] = 17;
        $transactiondata['ip_adress'] = $user->lastip;
        $transactiondata['zahlungsdetails'] = implode(',', array_keys($items));
        $transactiondata['zahlungsreferenz'] = $itemid;
        $transactiondata['session_lang'] = $_SESSION['SESSION']->forcelang;
        return $transactiondata;
    }

    /**
     * Returns the redirection urls
     * @return array
     */
    public function get_redirect_urls() {
        return [
          'success_url' => SUCCESS_URL,
          'error_url' => ERROR_URL,
          'feedback_url' => FEEDBACK_URL,
        ];
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @param  string $address
     * @return array|null
     */
    public function extract_address_parts($address) {
        $pattern = '/^(\d+)?\s*([\w\s]+?)\s*(\d+)?$/';
        if (preg_match($pattern, $address, $matches)) {
            $housenumber = null;
            $streetname = null;

            // Determine the position of the house number and street name.
            if (!empty($matches[1]) && is_numeric($matches[1])) {
                // Format: "123 Main St".
                $housenumber = $matches[1];
                $streetname = trim($matches[2]);
            } else if (!empty($matches[3]) && is_numeric($matches[3])) {
                // Format: "Main St 123".
                $housenumber = $matches[3];
                $streetname = trim($matches[2]);
            } else {
                // Only street name is available, no house number.
                $streetname = trim($matches[2]);
            }

            return [
                'number' => $housenumber,
                'name' => $streetname,
            ];
        } else {
            return null; // Address does not match expected format.
        }
    }
}
