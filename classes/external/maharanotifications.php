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
 * Web service definition for the local_mahoodle plugin
 *
 * @package    local_mahoodle
 * @copyright  2019 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author     Peter Spicer <peter.spicer@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mahoodle\external;

defined('MOODLE_INTERNAL') || die();

use core\message\message;
use core_user;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use moodle_url;
use stdClass;

/**
 * Web service definition for the local_mahoodle plugin
 *
 * @package    local_mahoodle
 * @copyright  2019 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author     Peter Spicer <peter.spicer@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class maharanotifications extends external_api {

    /**
     * Describes the parameters accepted by receive_notifications
     *
     * @return external_function_parameters
     */
    public static function receive_notifications_parameters() {
        return new external_function_parameters([
            'username'       => new external_value(PARAM_TEXT, 'User name to notify'),
            'maharanotifyid' => new external_value(PARAM_INT, 'Mahara\'s notification ID'),
            'subject'        => new external_value(PARAM_TEXT, 'Subject line of the notification'),
            'body'           => new external_value(PARAM_RAW, 'Body text of the notification as HTML'),
            'mnethost'       => new external_value(PARAM_URL, 'MNET host to return to'),
            'type'           => new external_value(PARAM_TEXT, 'Notification type'),
        ]);
    }

    /**
     * Handles receiving notifications from Mahara.
     *
     * @param string $username The recipient's username
     * @param int $maharanotifyid The notification ID in Mahara
     * @param string $subject The notification subject from Mahara
     * @param string $body The notification in HTML
     * @param string $mnethost The MNET host to jump back to for this notification
     * @return array As described in receive_notifications_returns
     */
    public static function receive_notifications($username, $maharanotifyid, $subject, $body, $mnethost, $type) {
        global $DB;

        // First, validate the parameters against the service definition.
        $params = self::validate_parameters(self::receive_notifications_parameters(), [
            'username' => $username,
            'maharanotifyid' => $maharanotifyid,
            'subject' => $subject,
            'body' => $body,
            'mnethost' => $mnethost,
            'type' => $type,
        ]);

        // Find the user.
        $user = $DB->get_record('user', ['username' => $username]);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Unknown user',
            ];
        }

        // Identify which MNET host this is.
        $mnethostdetails = $DB->get_record('mnet_host', ['wwwroot' => $mnethost]);
        if (!$mnethostdetails) {
            return [
                'success' => false,
                'error' => 'Unknown MNET host',
            ];
        }

        $contexturl = new moodle_url('/auth/mnet/jump.php');
        $contexturl->param('hostid', $mnethostdetails->id);
        $contexturl->param('wantsurl', 'module/multirecipientnotification/inbox.php?msg=' . $maharanotifyid . '&msgtype=' . $type);

        // This is a new notification.
        $message = new message;
        $message->component = 'local_mahoodle';
        if ($type == 'module_multirecipient_notification') {
            $message->name = 'maharamessage';
        } else {
            $message->name = 'maharanotification';
        }
        $message->courseid = SITEID;
        $message->notification = 1;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user->id;
        $message->subject = $subject;
        $message->fullmessage = clean_param($body, PARAM_CLEANHTML);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = clean_param($body, PARAM_CLEANHTML);
        $message->smallmessage = $subject;
        $message->contexturl = $contexturl;
        $message->contexturlname = $subject;
        $moodleid = message_send($message);

        // Now we bundle this into the database.
        $notify = new stdClass;
        $notify->userid = $user->id;
        $notify->moodleid = $moodleid;
        $notify->isread = 0;
        $notify->maharaid = $maharanotifyid;
        $notify->mnethost = $mnethostdetails->id;
        $notify->notifytype = $type;
        $DB->insert_record('local_mahoodle_mah_notify', $notify);

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * The return configuration for receive_notifications.
     *
     * @return external_single_structure
     */
    public static function receive_notifications_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Successful receipt'),
            'error' => new external_value(PARAM_TEXT, 'Error description if any'),
        ]);
    }

    /**
     * Describes the parameters accepted by mark_read
     *
     * @return external_function_parameters
     */
    public static function mark_read_parameters() {
        return new external_function_parameters([
            'maharanotifyid' => new external_value(PARAM_SEQUENCE, 'Mahara\'s notification IDs to mark read (e.g. 1,2,3)'),
            'mnethost'       => new external_value(PARAM_URL, 'MNET host to return to'),
            'type'           => new external_value(PARAM_TEXT, 'Notification type'),
        ]);
    }

    /**
     * Marks notifications as read
     *
     * @param string $maharanotifyid Notification IDs in 1,2,3 format from Mahara
     * @param string $mnethost The MNET host URL
     * @param string $type The type of notification, e.g. notification_internal_activity
     * @return array as described in mark_read_returns
     */
    public static function mark_read($maharanotifyid, $mnethost, $type) {
        global $CFG, $DB;

        // First, validate the parameters against the service definition.
        $params = self::validate_parameters(self::mark_read_parameters(), [
            'maharanotifyid' => $maharanotifyid,
            'mnethost' => $mnethost,
            'type' => $type,
        ]);

        require_once($CFG->dirroot . '/message/lib.php');

        // Identify which MNET host this is.
        $mnethostdetails = $DB->get_record('mnet_host', ['wwwroot' => $mnethost]);
        if (!$mnethostdetails) {
            return [
                'success' => false,
                'error' => 'Unknown MNET host',
            ];
        }

        if (!is_array($maharanotifyid)) {
            $maharanotifyid = explode(',', $maharanotifyid);
        }
        if (empty($maharanotifyid)) {
            return [
                'success' => false,
                'error' => 'No messages specified',
            ];
        }

        // Find the messages.
        list($notifysql, $notifyparams) = $DB->get_in_or_equal($maharanotifyid, SQL_PARAMS_NAMED);
        $sql = "
            SELECT id, moodleid, isread
              FROM {local_mahoodle_mah_notify}
             WHERE mnethost = :mnethost
               AND notifytype = :type
               AND maharaid $notifysql";
        $notifyparams += [
            'mnethost' => $mnethostdetails->id,
            'type' => $type,
        ];
        $notify = $DB->get_records_sql($sql, $notifyparams);

        foreach ($notify as $notification) {
            if ($notification->isread) {
                continue;
            }

            // Tell Moodle to mark it read, and then update our records.
            $message = $DB->get_record('message', ['id' => $notification->moodleid]);
            $newmessageid = message_mark_message_read($message, time());
            $notification->moodleid = $newmessageid;
            $notification->isread = 1;

            $DB->update_record('local_mahoodle_mah_notify', $notification);
        }

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * The return configuration for mark_read
     *
     * @return external_single_structure
     */
    public static function mark_read_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether marked read or not'),
            'error' => new external_value(PARAM_TEXT, 'Error description if any'),
        ]);
    }

    /**
     * Describes the parameters accepted by delete
     *
     * @return external_function_parameters
     */
    public static function delete_parameters() {
        return new external_function_parameters([
            'maharanotifyid' => new external_value(PARAM_SEQUENCE, 'Mahara\'s notification IDs to delete (e.g. 1,2,3)'),
            'mnethost'       => new external_value(PARAM_URL, 'MNET host to return to'),
            'type'           => new external_value(PARAM_TEXT, 'Notification type'),
        ]);
    }

    /**
     * Deletes notifications
     *
     * @param string $maharanotifyid Notification IDs in 1,2,3 format from Mahara
     * @param string $mnethost The MNET host URL
     * @param string $type The type of notification, e.g. notification_internal_activity
     * @return array as described in delete_returns
     */
    public static function delete($maharanotifyid, $mnethost, $type) {
        global $DB;

        // First, validate the parameters against the service definition.
        $params = self::validate_parameters(self::mark_read_parameters(), [
            'maharanotifyid' => $maharanotifyid,
            'mnethost' => $mnethost,
            'type' => $type,
        ]);

        // Identify which MNET host this is.
        $mnethostdetails = $DB->get_record('mnet_host', ['wwwroot' => $mnethost]);
        if (!$mnethostdetails) {
            return [
                'success' => false,
                'error' => 'Unknown MNET host',
            ];
        }

        if (!is_array($maharanotifyid)) {
            $maharanotifyid = explode(',', $maharanotifyid);
        }
        if (empty($maharanotifyid)) {
            return [
                'success' => false,
                'error' => 'No messages specified',
            ];
        }

        // Find the messages.
        list($notifysql, $notifyparams) = $DB->get_in_or_equal($maharanotifyid, SQL_PARAMS_NAMED);
        $sql = "
            SELECT id, moodleid, isread
              FROM {local_mahoodle_mah_notify}
             WHERE mnethost = :mnethost
               AND notifytype = :type
               AND maharaid $notifysql";
        $queryparams = $notifyparams + [
            'mnethost' => $mnethostdetails->id,
            'type' => $type,
        ];
        $notify = $DB->get_records_sql($sql, $queryparams);

        $unread = [];
        $read = [];

        // Moodle puts read and unread in different tables, with potentially different ids. We need to get which is which.
        foreach ($notify as $notification) {
            if ($notification->isread) {
                $read[] = $notification->moodleid;
            } else {
                $unread[] = $notification->moodleid;
            }
        }

        if (!empty($unread)) {
            // Deleting unread is fairly straightforward: first purge from the unread table.
            list($unreadsql, $unreadparams) = $DB->get_in_or_equal($unread, SQL_PARAMS_NAMED);
            $DB->delete_records_select('message', "id $unreadsql", $unreadparams);

            // Then purge from the working table for any pending processors.
            $DB->delete_records_select('message_working', "unreadmessageid $unreadsql", $unreadparams);
        }

        if (!empty($read)) {
            // Deleting read is simple, just the one table to purge.
            list($readsql, $readparams) = $DB->get_in_or_equal($read, SQL_PARAMS_NAMED);
            $DB->delete_records_select('message_read', "id $readsql", $readparams);
        }

        // Lastly, remove all the notifications from our local table pointing to them.
        $DB->delete_records_select('local_mahoodle_mah_notify', "maharaid $notifysql", $notifyparams);

        return [
            'success' => true,
            'error' => '',
        ];
    }

    /**
     * The return configuration for delete
     *
     * @return external_single_structure
     */
    public static function delete_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether deleted or not'),
            'error' => new external_value(PARAM_TEXT, 'Error description if any'),
        ]);
    }
}
