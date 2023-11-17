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

namespace tool_lcjsontrigger\lifecycle;

require_once($CFG->dirroot . '/admin/tool/lifecycle/trigger/lib.php');

use Exception;
use stdClass;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\response\trigger_response;
use tool_lifecycle\settings_type;
use tool_lifecycle\trigger\base_automatic;
use tool_lifecycle\trigger\instance_setting;

defined('MOODLE_INTERNAL') || die();

class trigger extends base_automatic {

    public function get_subpluginname()
    {
        return 'tool_lcjsontrigger';
    }

    public function get_plugin_description() {
        return get_string("pluginname", 'tool_lcjsontrigger');
    }

    public function check_course($course, $triggerid)
    {
        return trigger_response::trigger();
    }

    public function get_course_recordset_where($triggerid) {
        global $DB;

        // Retrieve the restricted courses from url feed.
        $restrictedcourses = $this->get_response_data($triggerid);

        // Look for moodle course using shortname.
        if (!empty($restrictedcourses)) {
            list($insql, $inparams) = $DB->get_in_or_equal($restrictedcourses, SQL_PARAMS_NAMED);
            $where = "{course}.shortname {$insql}";
            return array($where, $inparams);
        } else {
            return array('false', array());
        }
    }
    public function get_response_data($triggerid): array {
        // Feed url and headers.
        $courserestrictionfeed = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['courserestrictionfeed'];
        $feedheaders = settings_manager::get_settings($triggerid, settings_type::TRIGGER)['feedheaders'];
        $feedheaders = explode("\n", $feedheaders);

        // Retrieve the JSON feed.
        $jsondata = $this->get_json_feed($courserestrictionfeed, $feedheaders);

        // Return the data as array.
        $jsondata = json_decode($jsondata, true);
        return $jsondata['responseData'] ?? [];
    }

    protected function get_json_feed($feedurl, $headers) {
        // Unit test only.
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            return '{"responseData":["course1","course2"]}';
        }

        // Retrieve the JSON feed.
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $feedurl);
        curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'MoodleBot/1.0');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = new stdClass();

        $data->rawjson = curl_exec($curl);
        $data->error = curl_error($curl);
        $data->errno = curl_errno($curl);
        $data->strerror = curl_strerror($data->errno);
        $data->httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        // Some cURL errors will not return a HTTP status code.
        if ($data->httpcode != 0) {
            mtrace("HTTP Status Code: " . $data->httpcode);
        }

        if (strpos((string) $data->httpcode, '5') === 0) {
            mtrace('Failure on feed server occurred.');
            return '';
        }

        if ($data->errno != 0) {
            mtrace("cURL error string: " . $data->strerror);
            mtrace("cURL error message: " . $data->error);
            throw new Exception(" cURL error: ($data->errno) $data->error, $data->strerror");
        }

        if (empty($data->rawjson)) {
            // 204 HTTP_NO_CONTENT
            if ($data->httpcode == 204) {
                mtrace("JSON feed has returned HTTP 204 No Content.");
            } else {
                mtrace("JSON feed is empty!");
                throw new Exception(" JSON feed is empty! ($data->httpcode)");
            }
        }

        return $data->rawjson;
    }

    public function instance_settings() {
        return array(
            new instance_setting('courserestrictionfeed', PARAM_URL, true),
            new instance_setting('feedheaders', PARAM_TEXT, true),
        );
    }

    public function extend_add_instance_form_definition($mform) {
        // To enter the student course restriction feed url.
        $mform->addElement('text', 'courserestrictionfeed', get_string('courserestrictionfeed', 'tool_lcjsontrigger'));
        $mform->setType('courserestrictionfeed', PARAM_URL);
        $mform->addRule('courserestrictionfeed', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('courserestrictionfeed', 'courserestrictionfeed', 'tool_lcjsontrigger');

        // Feed header.
        $elementname = 'feedheaders';
        $mform->addElement('textarea', $elementname, get_string('feedheaders', 'tool_lcjsontrigger'));
        $mform->addHelpButton($elementname, 'feedheaders', 'tool_lcjsontrigger');
        $mform->setType($elementname, PARAM_TEXT);
    }
}
