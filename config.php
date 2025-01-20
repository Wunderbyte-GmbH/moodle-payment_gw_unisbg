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

global $CFG;
define('SUCCESS_URL', $CFG->wwwroot . '/payment/gateway/unisbg/checkout.php?status=1');
define('ERROR_URL', $CFG->wwwroot . '/payment/gateway/unisbg/checkout.php?status=0');
define('FEEDBACK_URL', $CFG->wwwroot . '/payment/gateway/unisbg/success_notify.php');
define('PAYMENT_PURPOSE', 17);
define('ENCRYPTIONMETHOD', 'aes-256-cbc');
define('AESKEY', '2325DC7118BAE579C696E88EC0DB430A');
