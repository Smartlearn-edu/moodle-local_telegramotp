<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_telegramotp\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_telegramotp.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    /**
     * Return metadata about data stored and shared by this plugin.
     *
     * @param collection $collection The initialized metadata collection.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_telegramotp_requests',
            [
                'phone'        => 'privacy:metadata:requests:phone',
                'email'        => 'privacy:metadata:requests:email',
                'request_id'   => 'privacy:metadata:requests:request_id',
                'ip_address'   => 'privacy:metadata:requests:ip_address',
                'status'       => 'privacy:metadata:requests:status',
                'attempts'     => 'privacy:metadata:requests:attempts',
                'timecreated'  => 'privacy:metadata:requests:timecreated',
                'timemodified' => 'privacy:metadata:requests:timemodified',
            ],
            'privacy:metadata:requests'
        );

        $collection->add_external_location_link(
            'telegram_gateway',
            [
                'phone_number' => 'privacy:metadata:telegram_gateway:phone_number',
            ],
            'privacy:metadata:telegram_gateway'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user ID to search for.
     * @return contextlist The context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $user = $DB->get_record('user', ['id' => $userid], 'id, email, phone1');

        if (!$user) {
            return $contextlist;
        }

        $params = [];
        $wheres = [];

        if (!empty($user->email)) {
            $wheres[] = 'email = :email';
            $params['email'] = $user->email;
        }

        if (!empty($user->phone1)) {
            $wheres[] = 'phone = :phone';
            $params['phone'] = $user->phone1;
        }

        if (!empty($wheres)) {
            $sql = implode(' OR ', $wheres);
            if ($DB->record_exists_select('local_telegramotp_requests', $sql, $params)) {
                $contextlist->add_from_sql(
                    "SELECT c.id FROM {context} c WHERE c.contextlevel = :level",
                    ['level' => CONTEXT_SYSTEM]
                );
            }
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof context_system) {
            $sql = "SELECT u.id
                      FROM {user} u
                      JOIN {local_telegramotp_requests} r
                        ON (r.email = u.email OR r.phone = u.phone1)";
            $userlist->add_from_sql('id', $sql, []);
        }
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $params = [];
        $wheres = [];

        if (!empty($user->email)) {
            $wheres[] = 'email = :email';
            $params['email'] = $user->email;
        }

        if (!empty($user->phone1)) {
            $wheres[] = 'phone = :phone';
            $params['phone'] = $user->phone1;
        }

        if (empty($wheres)) {
            return;
        }

        $sql = implode(' OR ', $wheres);
        $records = $DB->get_records_select('local_telegramotp_requests', $sql, $params);

        if ($records) {
            $data = [];
            foreach ($records as $record) {
                $data[] = [
                    'phone'        => $record->phone,
                    'email'        => $record->email,
                    'status'       => $record->status,
                    'attempts'     => $record->attempts,
                    'timecreated'  => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            $context = context_system::instance();
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_telegramotp')],
                (object) ['requests' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context instanceof context_system) {
            $DB->delete_records('local_telegramotp_requests');
        }
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $params = [];
        $wheres = [];

        if (!empty($user->email)) {
            $wheres[] = 'email = :email';
            $params['email'] = $user->email;
        }

        if (!empty($user->phone1)) {
            $wheres[] = 'phone = :phone';
            $params['phone'] = $user->phone1;
        }

        if (!empty($wheres)) {
            $sql = implode(' OR ', $wheres);
            $DB->delete_records_select('local_telegramotp_requests', $sql, $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context instanceof context_system) {
            $userids = $userlist->get_userids();
            if (!empty($userids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($userids);
                $users = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, email, phone1');

                foreach ($users as $user) {
                    $params = [];
                    $wheres = [];
                    if (!empty($user->email)) {
                        $wheres[] = 'email = :email';
                        $params['email'] = $user->email;
                    }
                    if (!empty($user->phone1)) {
                        $wheres[] = 'phone = :phone';
                        $params['phone'] = $user->phone1;
                    }
                    if (!empty($wheres)) {
                        $sql = implode(' OR ', $wheres);
                        $DB->delete_records_select('local_telegramotp_requests', $sql, $params);
                    }
                }
            }
        }
    }
}
