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
 * This page prints a particular instance of scheduler and handles top level interactions
 *
 * @package    mod_scheduler
 * @copyright  2014 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_scheduler\model\scheduler;

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/scheduler/lib.php');
require_once($CFG->dirroot . '/mod/scheduler/locallib.php');
require_once($CFG->dirroot . '/mod/scheduler/renderable.php');

// Read common request parameters.
$id = optional_param('id', '', PARAM_INT);    // Course Module ID - if it's not specified, must specify 'a', see below.
$action = optional_param('what', 'view', PARAM_ALPHA);
$subaction = optional_param('subaction', '', PARAM_ALPHA);
$offset = optional_param('offset', -1, PARAM_INT);
$istutor = optional_param('istutor', 0, PARAM_BOOL);

if ($id) {
    $cm = get_coursemodule_from_id('scheduler', $id, 0, false, MUST_EXIST);
    $scheduler = scheduler::load_by_coursemodule_id($id);
} else { 
    $a = required_param('a', PARAM_INT);     // Scheduler ID.
    $scheduler = scheduler::load_by_id($a);
    $cm = $scheduler->get_cm();
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);


require_login($course->id, true, $cm);
$context = context_module::instance($cm->id);
$permissions = new \mod_scheduler\permission\scheduler_permissions($context, $USER->id);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/scheduler/view.php', array('id' => $cm->id, 'istutor' => $istutor));

$output = $PAGE->get_renderer('mod_scheduler');
if (groups_get_activity_groupmode($cm) || !$permissions->can_see_all_slots()) {
    $defaultsubpage = 'myappointments';
} else {
    $defaultsubpage = 'allappointments';
}
$subpage = optional_param('subpage', $defaultsubpage, PARAM_ALPHA);


// Print the page header.

$title = $course->shortname . ': ' . format_string($scheduler->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
// Route to screen.

$teachercaps = ['mod/scheduler:manage', 'mod/scheduler:manageallappointments', 'mod/scheduler:canseeotherteachersbooking'];
$isteacher = has_any_capability($teachercaps, $context);
$isstudent = has_capability('mod/scheduler:viewslots', $context);
if ($istutor) {
    include($CFG->dirroot . '/mod/scheduler/teacherview.php');
} else {
    include($CFG->dirroot . '/mod/scheduler/studentview.php');
}