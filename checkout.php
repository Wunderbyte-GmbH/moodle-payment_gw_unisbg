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

use local_shopping_cart\local\cartstore;
use paygw_unisbg\output\checkout;

require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$status = required_param('status', PARAM_RAW);

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}
if ($status == 1) {
    $cartstore = cartstore::instance($USER->id);
    $cartstore->delete_all_items();
}
// Check if optionid is valid.
$PAGE->set_context($context);

$title = get_string('checkout', 'paygw_unisbg');

$PAGE->set_url('/payment/gateway/unisbg/checkout.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('paygw_unisbg_checkout');

echo $OUTPUT->header();

$output = $PAGE->get_renderer('paygw_unisbg');
$data = new checkout($status);

echo $output->render_checkout($data);

echo $OUTPUT->footer();
