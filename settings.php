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
 * Beanstalk queue settings.
 *
 * @package    queue_beanstalk
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings->add(new admin_setting_configtext(
        'queue_beanstalk/hostname', get_string('hostname', 'queue_beanstalk'), '', 'localhost')
    );

    $settings->add(new admin_setting_configtext(
        'queue_beanstalk/port', get_string('port', 'queue_beanstalk'), '', '11300')
    );

    $settings->add(new admin_setting_configtext(
        'queue_beanstalk/timeout', get_string('timeout', 'queue_beanstalk'), '', '300')
    );

    $settings->add(new admin_setting_configtext(
        'queue_beanstalk/tubename', get_string('tubename', 'queue_beanstalk'), '', 'moodle')
    );


    $ADMIN->add('reports', new admin_externalpage(
        'beanstalktaskstats',
        get_string('pluginname', 'queue_beanstalk'),
        new \moodle_url("/admin/tool/adhoc/queue/beanstalk/index.php")
    ));
}
