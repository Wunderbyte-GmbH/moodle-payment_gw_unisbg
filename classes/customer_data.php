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

use mod_booking\singleton_service;

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
class customer_data {
    /**
     * @var object base URL
     */
    private $user;
    /**
     * @var string
     */
    private $usercategory;

    /**
     * @var string base URL
     */
    private $internalscope = 'sbg.ac.at';

    /**
     * @var array base URL
     */
    private $internallabels = [
        'student',
        'staff',
    ];

    /**
     * customer_data constructor
     *
     */
    public function __construct() {
        global $USER;
        $this->user = singleton_service::get_instance_of_user($USER->id, true);
        $this->usercategory = $this->set_usercategory();
    }
    /**
     * Creates a checkout with the Provider given an array of items
     * @return string
     */
    private function set_usercategory() {
        $pricecategoryfield = get_config('booking', 'pricecategoryfield');
        return $this->user->profile[$pricecategoryfield] ?? '';
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @return bool
     */
    public function is_intern() {
        if (
            in_array($this->usercategory, $this->internallabels) &&
            $this->is_scope_internal()
        ) {
            return true;
        }
        return false;
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @return bool
     */
    private function is_scope_internal() {
        $userscope = explode('@', $this->user->username)[1] ?? '';
        return $userscope == $this->internalscope;
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @param array $transactiondata
     * @return void
     */
    public function set_intern_data(&$transactiondata) {
        $transactiondata['benutzername'] = $this->extract_username();
        return;
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @return string
     */
    public function extract_username() {
        return explode('@', $this->user->username)[0];
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @param array $transactiondata
     * @return void
     */
    public function set_extern_data(&$transactiondata) {
        $address = $this->extract_address_parts($this->user->address);
        $transactiondata['ext_pers_id'] = $this->user->id;
        $transactiondata['extp_vorname'] = $this->user->firstname;
        $transactiondata['extp_nachname'] = $this->user->lastname;
        $transactiondata['extp_strasse'] = $address['name'] ?? null;
        $transactiondata['extp_hausnummer'] = $address['number'] ?? null;
        $transactiondata['extp_stadt'] = $this->user->city ?? null;
        $transactiondata['extp_plz'] = '5020';
        $transactiondata['extp_land_iso'] = $this->user->country ?? null;
        $transactiondata['extp_mail'] = $this->user->email ?? null;
        return;
    }

    /**
     * Creates a checkout with the Provider given an array of items
     * @param  string $address
     * @return array|null
     */
    private function extract_address_parts($address) {
        $pattern = '/^([\w\s\-]+?)\s*(\d+)?$/';
        if (preg_match($pattern, $address, $matches)) {
              $streetname = isset($matches[1]) ? trim($matches[1]) : null;
              $housenumber = isset($matches[2]) ? $matches[2] : null;
              return [
                  'number' => $housenumber,
                  'name' => $streetname,
              ];
        } else {
            return null;
        }
    }
}
