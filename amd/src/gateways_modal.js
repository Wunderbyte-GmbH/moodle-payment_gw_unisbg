/* eslint-disable no-unused-vars */
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
 * This module is responsible for unisbg content in the gateways modal.
 *
 * @module     paygw_unisbg/gateway_modal
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';

/**
 * Creates and shows a modal that contains a placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async() => {
    const modal = await ModalFactory.create({
        body: await Templates.render('paygw_unisbg/unisbg_button_placeholder', {})
    });
    modal.show();
    return modal;
};


/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Promise.all([
            showModalWithPlaceholder(),
            Repository.getConfigForJs(component, paymentArea, itemId),
        ])
        .then(([modal, unisbgConfig]) => {
            return Promise.all([
                modal,
                unisbgConfig,
            ]);
        })
        .then(([modal, unisbgConfig]) => {
            location.href = unisbgConfig.url;
            return '';
        }).then(x => {
            const promise = new Promise(resolve => {
                window.addEventListener('onbeforeunload', (e) => {
                    promise.resolve();
                });
            });
            return promise;
        })
        .catch(error => {
            // eslint-disable-next-line no-console
            console.error('Error processing payment:', error);
            throw error;
        });
};