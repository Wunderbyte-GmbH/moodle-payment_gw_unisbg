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
 * The payment_successful event.
 *
 * @package paygw_unisbg
 * @copyright 2023 Georg Maißer, http://www.edulabs.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace paygw_unisbg\event;

/**
 * The delivery_event class.
 *
 * @property-read array $other { Extra information about event. }
 * @since Moodle 3.11
 * @copyright 2022 Georg Maißer <georg.maisser@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delivery_error extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('delivery_error', 'paygw_unisbg');
    }

    public function get_description() {
        return "The user with the id {$this->userid} has tried to pay, but an error occured on delivery: " . $this->other['message'];
    }

    public function get_url() {
        return new \moodle_url('/payment/gateway/unisbg/checkout.php');
    }
}
