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

namespace paygw_unisbg\output;
use plugin_renderer_base;


/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the booking module.
 *
 * @package paygw_unisbg
 * @copyright 2022 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /** Function to render the dashboard
     * Renderes the checkout template
     * @param stdClass $data
     * @return string
     */
    public function render_checkout($data) {
        $o = '';
        $data = $data->export_for_template($this);
        $o .= $this->render_from_template('paygw_unisbg/checkout', $data);
        return $o;
    }

}
