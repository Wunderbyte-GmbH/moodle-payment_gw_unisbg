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
 * unisbg repository module to encapsulate all of the AJAX requests that can be sent for unisbg.
 *
 * @module     paygw_unisbg/confirmpayment
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

export const init = (itemid, customer, component, paymentarea, ischeckstatus, tid) => {

    Ajax.call([{
        methodname: "paygw_unisbg_create_transaction_complete",
        args: {
            component,
            paymentarea,
            itemid,
            tid,
            token: '',
            customer,
            ischeckstatus
        },
        done: function(data) {
            if (data.success !== true) {
                require(['jquery'], function($) {
                    require(['core/str'], function(str) {
                        var strings = [
                            {
                                key: 'error',
                                component: 'paygw_unisbg'
                            },
                            {
                                key: 'proceed',
                                component: 'paygw_unisbg',
                            }
                        ];
                        var localStrings = str.get_strings(strings);
                        $.when(localStrings).done(function(localizedEditStrings) {
                            ModalFactory.create({
                                type: ModalFactory.types.CANCEL,
                                title: localizedEditStrings[0],
                                body: data.message,
                                buttons: {
                                    cancel: localizedEditStrings[1],
                                },
                            })
                            .then(function(modal) {
                                var root = modal.getRoot();
                                root.on(ModalEvents.cancel, function() {
                                    location.href = data.url;
                                });
                                modal.show();
                                return true;
                            }).catch({
                                // Debugging console.log(e);
                            });
                        });
                    });
                });
            } else {
                location.href = data.url;
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log("exxx:" + ex);
        },
    }]);

};