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
 * Web service definition for the mahoodle plugin
 *
 * @package    local_mahoodle
 * @copyright  2019 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author     Peter Spicer <peter.spicer@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mahoodle_receive_mahara_notifications' => [
        'classname' => 'local_mahoodle\external\maharanotifications',
        'methodname' => 'receive_notifications',
        'description' => 'Receives notifications from Mahara and issues them to users.',
        'type' => 'write',
    ],
    'local_mahoodle_read_mahara_notifications' => [
        'classname' => 'local_mahoodle\external\maharanotifications',
        'methodname' => 'mark_read',
        'description' => 'Receives notifications from Mahara about which notifications have been read.',
        'type' => 'write',
    ],
    'local_mahoodle_delete_mahara_notifications' => [
        'classname' => 'local_mahoodle\external\maharanotifications',
        'methodname' => 'delete',
        'description' => 'Delete Mahara notifications that were sent to Moodle, once deleted in Mahara',
        'type' => 'write',
    ],
];

$services = [
    'Mahara Notification Receiver' => [
        'functions' => [
            'local_mahoodle_receive_mahara_notifications',
            'local_mahoodle_read_mahara_notifications',
            'local_mahoodle_delete_mahara_notifications',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
    ]
];
