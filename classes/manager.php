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
 * Beanstalk API for the adhoc task manager.
 *
 * @package   queue_beanstalk
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace queue_beanstalk;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

/**
 * Beanstalk methods.
 *
 * @package   queue_beanstalk
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements \tool_adhoc\queue
{
    private $config;
    private $enabled;
    private $api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = get_config('queue_beanstalk');
        $this->enabled = \tool_adhoc\manager::is_enabled('beanstalk');
        if (isset($this->config->unavailable)) {
            if (time() - $this->config->unavailable < 18000) {
                // Beanstalk has been down in the last 5 minutes, don't try it.
                $this->enabled = false;
            } else {
                unset_config('unavailable', 'queue_beanstalk');
            }
        }

        try {
            if ($this->enabled) {
                $this->api = new Pheanstalk(
                    $this->config->hostname,
                    $this->config->port,
                    $this->config->timeout
                );
            }
        } catch (\Exception $e) {
            // Not ready?
            $this->enabled = false;
        }
    }

    /**
     * Push an item onto the queue.
     *
     * @param  int $id       ID of the adhoc task.
     * @param  int $priority Priority (higher = lower priority)
     * @param  int $timeout  Timeout for the task to complete.
     * @param  int $delay    Delay before executing task.
     * @return bool          [description]
     */
    public function push($id, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $timeout = 900, $delay = PheanstalkInterface::DEFAULT_DELAY) {
        return $this->putInTube($this->get_tube(), json_encode(array(
            'id' => $id
        )), $priority, $delay, $timeout);
    }

    /**
     * Magic.
     */
    public function __call($method, $arguments) {
        if (!$this->enabled) {
            return false;
        }

        $result = false;

        try {
            $result = call_user_func_array(array($this->api, $method), $arguments);
        } catch (\Pheanstalk\Exception\ConnectionException $e) {
            // Not ready?
            $this->enabled = false;
            set_config('unavailable', time(), 'queue_beanstalk');
        }

        return $result;
    }

    /**
     * Are we ready?
     */
    public function is_ready() {
        return $this->enabled;
    }

    /**
     * Return our tube name.
     */
    public function get_tube() {
        return $this->config->tubename;
    }

    /**
     * Initialize worker.
     */
    public function become_worker() {
        global $DB;

        if (!$this->enabled) {
            return false;
        }

        $runversion = $DB->get_field('config', 'value', array('name' => 'beanstalk_deploy'));

        $this->watch($this->get_tube());
        while ($job = $this->reserve(300)) {
            // Check the DB is still alive.
            try {
                $currentversion = $DB->get_field('config', 'value', array('name' => 'beanstalk_deploy'));

                if ($currentversion !== $runversion) {
                    throw new \moodle_exception("Beanstalk worker requires a restart.");
                }
            } catch (\Exception $e) {
                $this->release($job);
                exit(1);
            }

            $received = json_decode($job->getData());
            if (!isset($received->id)) {
                cli_writeln("Received invalid job: " . json_encode($received));
                $this->delete($job);

                continue;
            }

            // We have something to do!
            $task = $DB->get_record('task_adhoc', array('id' => $received->id));
            if ($task) {
                \tool_adhoc\manager::run_tasks(array($task), true);
                $this->delete($job);
            } else {
                cli_writeln("Could not find task {$received->id}.");
                $this->delete($job);
            }

            // Flush buffers.
            $this->flush();
        }
    }

    /**
     * Flushes various buffers.
     */
    private function flush() {
        // Flush log stores.
        get_log_manager(true);

        // Special case for splunk.
        if (class_exists('\\logstore_splunk\\splunk')) {
            $splunk = \logstore_splunk\splunk::instance();
            $splunk->flush();
        }
    }
}
