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
 * External xapi log store plugin
 *
 * @package    logstore_xapi
 * @copyright  2015 Jerrett Fowler <jfowler@charitylearning.org>
 *                  Ryan Smith <ryan.smith@ht2.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\log;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__.'/user/profile/lib.php');
require_once(__DIR__ . '/../../src/autoload.php');

use \tool_log\log\writer as log_writer;
use \tool_log\log\manager as log_manager;
use \tool_log\helper\store as helper_store;
use \tool_log\helper\reader as helper_reader;
use \tool_log\helper\buffered_writer as helper_writer;
use \core\event\base as event_base;
use \XREmitter\Controller as xapi_controller;
use \XREmitter\Repository as xapi_repository;
use \MXTranslator\Controller as translator_controller;
use \MXTranslator\Events\Event as Event;
use \LogExpander\Controller as moodle_controller;
use \LogExpander\Repository as moodle_repository;
use \TinCan\RemoteLRS as tincan_remote_lrs;
use \moodle_exception as moodle_exception;
use \stdClass as php_obj;
use \logstore_xapi\sync as syncHandler;

/**
 * This class processes events and enables them to be sent to a logstore.
 *
 */
class store extends php_obj implements log_writer {
    use helper_store;
    use helper_reader;
    use helper_writer;

    protected $loggingenabled = false;

    /** @var bool $logguests true if logging guest access */
    protected $logguests;

    /** @var array $routes An array of routes to include */
    protected $routes = [];

    /**
     * Constructs a new store.
     * @param log_manager $manager
     */
    public function __construct(log_manager $manager) {
        $this->helper_setup($manager);
        $this->logguests = $this->get_config('logguests', 1);
        $routes = $this->get_config('routes', '');
        $this->routes = $routes === '' ? [] : explode(',', $routes);
        $this->syncInstance = new syncHandler\sync();
        $_SERVER['token'] = $this->syncInstance->getLRSToken($this->get_config('username', ''),
            $this->get_config('password', ''), $this->get_config('tokenendpoint', 'http://10.236.173.83:6060/lrs/api/auth/login'))->token;
        $this->syncInstance->getLastestLRSEvent($this->get_config('syncendpoint', 'http://10.236.173.83:6060/lrs/api/event/sync/latest'),$_SERVER['token']);
    }

    /**
     * Should the event be ignored (not logged)? Overrides helper_writer.
     * @param event_base $event
     * @return bool
     *
     */
    protected function is_event_ignored(event_base $event) {
        $allowguestlogging = $this->get_config('logguests', 1);
        if ((!CLI_SCRIPT || PHPUNIT_TEST) && !$allowguestlogging && isguestuser()) {
            // Always log inside CLI scripts because we do not login there.
            return true;
        }

        $enabledevents = explode(',', $this->get_config('routes', ''));
        $isdisabledevent = !in_array($event->eventname, $enabledevents);
        return $isdisabledevent;
    }

    /**
     * Insert events in bulk to the database. Overrides helper_writer.
     * @param array $events raw event data
     */
    protected function insert_event_entries(array $events) {
        global $DB;

        // If in background mode, just save them in the database.
        if ($this->get_config('backgroundmode', false)) {
            $DB->insert_records('logstore_xapi_log', $events);
        } else {
            $this->process_events($events);
        }
    }

    public function get_max_batch_size() {
        return $this->get_config('maxbatchsize', 100);
    }

    public function process_events(array $events) {
        global $DB;
        global $CFG;
        require(__DIR__ . '/../../version.php');
        $logerror = function ($message = '') {
            debugging($message, DEBUG_NORMAL);
        };
        $loginfo = function ($message = '') {
            debugging($message, DEBUG_DEVELOPER);
        };
        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => [
                'source_url' => 'http://moodle.org',
                'source_name' => 'Moodle',
                'source_version' => $CFG->release,
                'source_lang' => 'en',
                'send_mbox' => $this->get_config('mbox', false),
                'send_response_choices' => $this->get_config('sendresponsechoices', false),
                'send_short_course_id' => $this->get_config('shortcourseid', false),
                'send_course_and_module_idnumber' => $this->get_config('sendidnumber', false),
                'send_username' => $this->get_config('send_username', false),
                'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
                'plugin_version' => $plugin->release,
                'repo' => new \src\transformer\repos\MoodleRepository($DB),
                'app_url' => $CFG->wwwroot,
            ],
            'loader' => [
                'loader' => 'moodle_curl_lrs',
                'lrs_endpoint' => $this->get_config('endpoint', ''),
                'lrs_username' => $this->get_config('username', ''),
                'lrs_password' => $this->get_config('password', ''),
                'lrs_max_batch_size' => $this->get_max_batch_size(),
            ],
        ];
        $loadedevents = \src\handler($handlerconfig, $events);
        return $loadedevents;
    }

    /**
     * Determines if a connection exists to the store.
     * @return boolean
     */
    public function is_logging() {
        try {
            $this->connect_xapi_repository();
            return true;
        } catch (moodle_exception $ex) {
            debugging('Cannot connect to LRS: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Creates a connection the xAPI store.
     * @return xapi_repository
     */
    private function connect_xapi_repository() {
        global $CFG;
        global $USER;
        $remotelrs = new tincan_remote_lrs(
            $this->get_config('endpoint', ''),
            '1.0.1',
            $this->get_config('username', ''),
            $this->get_config('password', '')
        );
        $headers = $remotelrs->getHeaders();
        profile_load_custom_fields($USER);
        if($headers != null) {
            $headers->userAddress = $USER->profile['blockchainAddress'];
            debugging('Not Empty Headers: ' .$headers->userAddress, DEBUG_DEVELOPER);
            $remotelrs->setHeaders($headers);
        } else {
            $headers = new php_obj();
            $headers->userAddress = $USER->profile['blockchainAddress'];
            debugging('Empty Headers: ' .$headers->userAddress, DEBUG_DEVELOPER);
            $remotelrs->setHeaders($headers);
        }
        if (!empty($CFG->proxyhost)) {
            $remotelrs->setProxy($CFG->proxyhost.':'.$CFG->proxyport);
        }
        return new xapi_repository($remotelrs);
    }

    /**
     * Creates a connection the xAPI store.
     * @return moodle_repository
     */
    private function connect_moodle_repository() {
        global $DB;
        global $CFG;
        return new moodle_repository($DB, $CFG);
    }
}
