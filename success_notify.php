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

$encryptedpayload = optional_param('zahlungsdetails', '', PARAM_RAW);

// Todo: Decrypt.

// todo: Via tid, return $itemmid & $userid from unisbg_openorderstable.

// Todo Call moodle webservice on result SUCCESS.

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

redirect($notifyurl->out());
