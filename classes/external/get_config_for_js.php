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
 * This class contains a list of webservice functions related to the unisbg payment gateway.
 *
 * @package    paygw_unisbg
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_unisbg\external;



use context_system;
use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\shopping_cart_history;
use paygw_unisbg\event\payment_added;
use stdClass;
use paygw_unisbg\unisbg_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * The payment_added event.
 *
 * @package     paygw_unisbg
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Jacob Viertel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_config_for_js extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }


    /**
     * Returns the config values required by the unisbg JavaScript SDK.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string[]
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $CFG, $USER, $SESSION, $DB;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'unisbg');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('unisbg');

        $language = $USER->lang;
        $secret = get_config('paygw_unisbg', 'tokenforverification');
        $root = $CFG->wwwroot;
        $environment = $config['environment'];

        // Get all items from shoppingcart.
        $items = shopping_cart_history::return_data_via_identifier($itemid);
        $ushelper = new unisbg_helper($environment);

        $now = time();
        $amount = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $starttransactiondata = $ushelper->get_starttransaction_data(
            $amount,
            $itemid,
            $items
        );
        $provider = $ushelper->init_transaction($starttransactiondata);
        if ($provider == '401' || $provider == '500') {
            $ushelper->set_access_token($config);
            $provider = $ushelper->init_transaction($starttransactiondata);
        }

        $record = new stdClass();
        $record->tid = $provider['transactionID'];
        $record->itemid = $itemid;
        $record->userid = intval($USER->id);
        $record->status = 0;
        $record->price = $amount;
        $record->timecreated = $now;
        $record->timemodified = $now;

        // Check for duplicate.
        if (!$existingrecord = $DB->get_record('paygw_unisbg_openorders', ['itemid' => $itemid, 'userid' => $USER->id])) {
            $id = $DB->insert_record('paygw_unisbg_openorders', $record);

            // We trigger the payment_added event.
            $context = context_system::instance();
            $event = payment_added::create([
                'context' => $context,
                'userid' => $USER->id,
                'objectid' => $id,
                'other' => [
                    'orderid' => $itemid,
                ],
            ]);
            $event->trigger();
        } else {
            $itemid = $existingrecord->tid;
        }

        return [
            'clientid' => $config['clientid'],
            'brandname' => $config['brandname'],
            'cost' => $amount,
            'currency' => $payable->get_currency(),
            'rooturl' => $root,
            'environment' => $environment,
            'language' => $language,
            'providerobject' => json_encode($provider),
            'cartid' => $itemid,
            'url' => $provider['zahlungsurl'],
        ];
    }


    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'clientid' => new external_value(PARAM_TEXT, 'unisbg client ID'),
            'brandname' => new external_value(PARAM_TEXT, 'Brand name'),
            'cost' => new external_value(PARAM_FLOAT, 'Cost with gateway surcharge'),
            'currency' => new external_value(PARAM_TEXT, 'Currency'),
            'rooturl' => new external_value(PARAM_TEXT, 'Moodle Root URI'),
            'environment' => new external_value(PARAM_TEXT, 'Prod or Sandbox'),
            'language' => new external_value(PARAM_TEXT, 'language'),
            'providerobject' => new external_value(PARAM_TEXT, 'providers'),
            'cartid' => new external_value(PARAM_INT, 'unique transaction id'),
            'url' => new external_value(PARAM_TEXT, 'zahlungsurl'),
        ]);
    }
}
