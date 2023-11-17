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

namespace tool_lcjsontrigger\tests\generator;

use moodle_exception;
use stdClass;
use testing_module_generator;
use tool_lifecycle\local\entity\trigger_subplugin;
use tool_lifecycle\local\entity\workflow;
use tool_lifecycle\local\manager\settings_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\settings_type;

/**
 * tool_lcjsontrigger generator tests
 *
 * @package    tool_lcjsontrigger
 * @category   test
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lcjsontrigger_generator extends testing_module_generator
{

    /**
     * Creates a trigger lcenddatedelaytrigger.
     *
     * @return trigger_subplugin the created lcenddatedelaytrigger trigger.
     * @throws moodle_exception
     */
    public static function create_trigger_with_workflow()
    {
        // Create Workflow.
        $record = new stdClass();
        $record->id = null;
        $record->title = 'myworkflow';
        $workflow = workflow::from_record($record);
        workflow_manager::insert_or_update($workflow);

        // Create trigger.
        $record = new stdClass();
        $record->subpluginname = 'tool_lcjsontrigger';
        $record->instancename = 'tool_lcjsontrigger';
        $record->workflowid = $workflow->id;
        $trigger = trigger_subplugin::from_record($record);
        trigger_manager::insert_or_update($trigger);

        // Set delay setting.
        $settings = new stdClass();
        $settings->courserestrictionfeed = 'https://localhost';
        $settings->feedheaders = '';
        settings_manager::save_settings($trigger->id, settings_type::TRIGGER, $trigger->subpluginname, $settings);

        return $trigger;
    }
}
