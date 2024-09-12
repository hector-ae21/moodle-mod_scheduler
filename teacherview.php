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
 * Contains various sub-screens that a teacher can see.
 *
 * @package    mod_scheduler
 * @copyright  2016 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \mod_scheduler\model\scheduler;

/**
 * Print a selection box of existing slots to be scheduler in
 *
 * @param scheduler $scheduler
 * @param int $studentid student to schedule
 * @param int $groupid group to schedule
 */
function scheduler_print_schedulebox(scheduler $scheduler, $studentid, $groupid = 0) {
    global $output;

    $availableslots = $scheduler->get_slots_available_to_student($studentid);

    $startdatemem = '';
    $starttimemem = '';
    $availableslotsmenu = array();
    foreach ($availableslots as $slot) {
        $startdatecnv = $output->userdate($slot->starttime);
        $starttimecnv = $output->usertime($slot->starttime);

        $startdatestr = ($startdatemem != '' && $startdatemem == $startdatecnv) ? "-----------------" : $startdatecnv;
        $starttimestr = ($starttimemem != '' && $starttimemem == $starttimecnv) ? '' : $starttimecnv;

        $startdatemem = $startdatecnv;
        $starttimemem = $starttimecnv;

        $url = new moodle_url('/mod/scheduler/view.php',
                        array('id' => $scheduler->cmid, 'slotid' => $slot->id, 'sesskey' => sesskey(), 'istutor' => 1));
        if ($groupid) {
            $url->param('what', 'schedulegroup');
            $url->param('subaction', 'dochooseslot');
            $url->param('groupid', $groupid);
        } else {
            $url->param('what', 'schedule');
            $url->param('subaction', 'dochooseslot');
            $url->param('studentid', $studentid);
        }
        $availableslotsmenu[$url->out()] = "$startdatestr $starttimestr";
    }

    $chooser = new url_select($availableslotsmenu);

    if ($availableslots) {
        echo $output->box_start();
        echo $output->heading(get_string('chooseexisting', 'scheduler'), 3);
        echo $output->render($chooser);
        echo $output->box_end();
    }
}

// Load group restrictions.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = false;
if ($groupmode) {
    $currentgroup = groups_get_activity_group($cm, true);
}

// All group arrays in the following are in the format used by groups_get_all_groups.
// The special value '' (empty string) is used to signal "all groups" (no restrictions).

// Find groups which the current teacher can see ($groupsicansee, $groupsicurrentlysee).
// $groupsicansee contains all groups that a teacher potentially has access to.
// $groupsicurrentlysee may be restricted by the user to one group, using the drop-down box.
$userfilter = $USER->id;
if (has_capability('moodle/site:accessallgroups', $context)) {
    $userfilter = 0;
}
$groupsicansee = '';
$groupsicurrentlysee = '';
if ($groupmode) {
    if ($userfilter) {
        $groupsicansee = groups_get_all_groups($COURSE->id, $userfilter, $cm->groupingid);
    }
    $groupsicurrentlysee = $groupsicansee;
    if ($currentgroup) {
        if ($userfilter && !groups_is_member($currentgroup, $userfilter)) {
            $groupsicurrentlysee = array();
        } else {
            $cgobj = groups_get_group($currentgroup);
            $groupsicurrentlysee = array($currentgroup => $cgobj);
        }
    }
}

// Find groups which the current teacher can schedule as a group ($groupsicanschedule).
$groupsicanschedule = array();
if ($scheduler->is_group_scheduling_enabled()) {
    $groupsicanschedule = groups_get_all_groups($COURSE->id, $userfilter, $scheduler->bookingrouping);
}

// Find groups which can book an appointment with the current teacher ($groupsthatcanseeme).

$groupsthatcanseeme = '';
if ($groupmode) {
    $groupsthatcanseeme = groups_get_all_groups($COURSE->id, $USER->id, $cm->groupingid);
}


$taburl = new moodle_url('/mod/scheduler/view.php', array('id' => $scheduler->cmid, 'what' => 'view', 'subpage' => $subpage, 'istutor' => 1));

$baseurl = new moodle_url('/mod/scheduler/view.php', array(
        'id' => $scheduler->cmid,
        'subpage' => $subpage,
        'offset' => $offset,
        'istutor' => 1,
));

// The URL that is used for jumping back to the view (e.g., after an action is performed).
$viewurl = new moodle_url($baseurl, array('what' => 'view', 'istutor' => 1));

$PAGE->set_url($viewurl);

if ($action != 'view') {
    require_once($CFG->dirroot.'/mod/scheduler/slotforms.php');
    require_once($CFG->dirroot.'/mod/scheduler/teacherview.controller.php');
}

/************************************ View : Update single slot form ****************************************/
if ($action == 'updateslot') {

    $slotid = required_param('slotid', PARAM_INT);
    $slot = $scheduler->get_slot($slotid);
    $permissions->ensure($permissions->can_edit_slot($slot));


    if ($slot->starttime % 300 !== 0 || $slot->duration % 5 !== 0) {
        $timeoptions = array('step' => 1, 'optional' => false);
    } else {
        $timeoptions = array('step' => 5, 'optional' => false);
    }

    $actionurl = new moodle_url($baseurl, array('what' => 'updateslot', 'slotid' => $slotid, 'istutor' => 1));

    $mform = new scheduler_editslot_form($actionurl, $scheduler, $cm, $groupsicansee, array(
            'slotid' => $slotid,
            'timeoptions' => $timeoptions)
        );
    $data = $mform->prepare_formdata($slot);
    $mform->set_data($data);

    if ($mform->is_cancelled()) {
        redirect($viewurl);
    } else if ($formdata = $mform->get_data()) {
        $mform->save_slot($slotid, $formdata);
        redirect($viewurl,
                 get_string('slotupdated', 'scheduler'),
                 0,
                 \core\output\notification::NOTIFY_SUCCESS);
    } else {
        echo $output->header();
        echo $output->heading(get_string('updatesingleslot', 'scheduler'));
        $mform->display();
        echo $output->footer($course);
        die;
    }

}
/************************************ Add session multiple slots form ****************************************/
if ($action == 'addsession') {

    $permissions->ensure($permissions->can_edit_own_slots());

    $actionurl = new moodle_url($baseurl, array('what' => 'addsession', 'istutor' => 1));

    if (!$scheduler->has_available_teachers()) {
        throw new moodle_exception('needteachers', 'scheduler', $viewurl);
    }

    $mform = new scheduler_addsession_form($actionurl, $scheduler, $cm, $groupsicansee);

    if ($mform->is_cancelled()) {
        redirect($viewurl);
    } else if ($formdata = $mform->get_data()) {
        scheduler_action_doaddsession($scheduler, $formdata, $viewurl);
    } else {
        echo $output->header();
        echo $output->heading(get_string('addsession', 'scheduler'));
        $mform->display();
        echo $output->footer();
        die;
    }
}

/************************************ Schedule a student form ***********************************************/
if ($action == 'schedule') {
    $permissions->ensure($permissions->can_edit_own_slots());

    echo $output->header();

    if ($subaction == 'dochooseslot') {
        $slotid = required_param('slotid', PARAM_INT);
        $slot = $scheduler->get_slot($slotid);
        $studentid = required_param('studentid', PARAM_INT);

        $actionurl = new moodle_url($baseurl, array('what' => 'updateslot', 'slotid' => $slotid, 'istutor' => 1));

        $repeats = $slot->get_appointment_count() + 1;
        $mform = new scheduler_editslot_form($actionurl, $scheduler, $cm, $groupsicansee,
                                             array('slotid' => $slotid, 'repeats' => $repeats));
        $data = $mform->prepare_formdata($slot);
        $data->studentid[] = $studentid;
        $mform->set_data($data);

        echo $output->heading(get_string('updatesingleslot', 'scheduler'), 2);
        $mform->display();

    }

    echo $output->footer();
    die();
}
/************************************ Schedule a whole group in form ***********************************************/
if ($action == 'schedulegroup') {

    $permissions->ensure($permissions->can_edit_own_slots());

    $groupid = required_param('groupid', PARAM_INT);
    $group = $DB->get_record('groups', array('id' => $groupid), '*', MUST_EXIST);
    $members = groups_get_members($groupid);

    echo $output->header();

    if ($subaction == 'dochooseslot') {

        $slotid = required_param('slotid', PARAM_INT);
        $groupid = required_param('groupid', PARAM_INT);
        $slot = $scheduler->get_slot($slotid);

        $actionurl = new moodle_url($baseurl, array('what' => 'updateslot', 'slotid' => $slotid, 'istutor' => 1));

        $repeats = $slot->get_appointment_count() + count($members);
        $mform = new scheduler_editslot_form($actionurl, $scheduler, $cm, $groupsicansee,
                                             array('slotid' => $slotid, 'repeats' => $repeats));
        $data = $mform->prepare_formdata($slot);
        foreach ($members as $member) {
            $data->studentid[] = $member->id;
        }
        $mform->set_data($data);

        echo $output->heading(get_string('updatesingleslot', 'scheduler'), 3);
        $mform->display();

    } 
    echo $output->footer();
    die();
}

/************************************ Send message to students ****************************************/
if ($action == 'sendmessage') {
    $permissions->ensure($permissions->can_edit_own_slots());

    require_once($CFG->dirroot.'/mod/scheduler/message_form.php');

    $template = optional_param('template', 'none', PARAM_ALPHA);
    $recipientids = required_param('recipients', PARAM_SEQUENCE);

    $actionurl = new moodle_url('/mod/scheduler/view.php',
            array('what' => 'sendmessage', 'id' => $cm->id, 'subpage' => $subpage,
                  'template' => $template, 'recipients' => $recipientids, 'istutor' => 1));

    $templatedata = array();
    if ($template != 'none') {
        $vars = scheduler_messenger::get_scheduler_variables($scheduler, null, $USER, null, $COURSE, null);
        $templatedata['subject'] = scheduler_messenger::compile_mail_template($template, 'subject', $vars);
        $templatedata['body'] = scheduler_messenger::compile_mail_template($template, 'html', $vars);
    }
    $templatedata['recipients'] = $DB->get_records_list('user', 'id', explode(',', $recipientids), 'lastname,firstname');

    $mform = new scheduler_message_form($actionurl, $scheduler, $templatedata);

    if ($mform->is_cancelled()) {
        redirect($viewurl);
    } else if ($formdata = $mform->get_data()) {
        scheduler_action_dosendmessage($scheduler, $formdata, $viewurl);
    } else {
        echo $output->header();
        echo $output->heading(get_string('sendmessage', 'scheduler'));
        $mform->display();
        echo $output->footer();
        die;
    }
}


/****************** Standard view ***********************************************/


// Trigger view event.
\mod_scheduler\event\appointment_list_viewed::create_from_scheduler($scheduler)->trigger();


// Print top tabs.

$actionurl = new moodle_url($viewurl, array('sesskey' => sesskey(), 'istutor' => 1));

$inactive = array();
if ($DB->count_records('scheduler_slots', array('schedulerid' => $scheduler->id)) <=
         $DB->count_records('scheduler_slots', array('schedulerid' => $scheduler->id, 'teacherid' => $USER->id)) ) {
    // We are alone in this scheduler.
    $inactive[] = 'allappointments';
    if ($subpage = 'allappointments') {
        $subpage = 'myappointments';
    }
}

echo $output->header();

if ($groupmode) {
    if ($subpage == 'allappointments') {
        groups_print_activity_menu($cm, $taburl);
    } else {
        $a = new stdClass();
        $a->groupmode = get_string($groupmode == VISIBLEGROUPS ? 'groupsvisible' : 'groupsseparate');
        $groupnames = array();
        foreach ($groupsthatcanseeme as $id => $group) {
            $groupnames[] = $group->name;
        }
        $a->grouplist = implode(', ', $groupnames);
        $messagekey = $groupsthatcanseeme ? 'groupmodeyourgroups' : 'groupmodeyourgroupsempty';
        $message = get_string($messagekey, 'scheduler', $a);
        echo html_writer::div($message, 'groupmodeyourgroups');
    }
}


if ($subpage == 'allappointments') {
    $teacherid = 0;
    $slotgroup = $currentgroup;
} else {
    $teacherid = $USER->id;
    $slotgroup = 0;
    $subpage = 'myappointments';
}
$sqlcount = $scheduler->count_slots_for_teacher($teacherid, $slotgroup);

$pagesize = 25;
if ($offset == -1) {
    if ($sqlcount > $pagesize) {
        $offsetcount = $scheduler->count_slots_for_teacher($teacherid, $slotgroup, true);
        $offset = floor($offsetcount / $pagesize);
    } else {
        $offset = 0;
    }
}
if ($offset * $pagesize >= $sqlcount && $sqlcount > 0) {
    $offset = floor(($sqlcount - 1) / $pagesize);
}

$slots = $scheduler->get_slots_for_teacher($teacherid, $slotgroup, $offset * $pagesize, $pagesize);

echo $output->heading(get_string('slots', 'scheduler'));

// Print instructions and button for creating slots.
$key = ($slots) ? 'addslot' : 'welcomenewteacher';
echo html_writer::div(get_string($key, 'scheduler'));


$commandbar = new scheduler_command_bar();
$commandbar->title = get_string('actions', 'scheduler');

$addbuttons = array();
$addbuttons[] = $commandbar->action_link(new moodle_url($actionurl, array('what' => 'addsession', 'istutor' =>1)), 'addsession', 't/add');
$commandbar->add_group(get_string('addcommands', 'scheduler'), $addbuttons);

echo $output->render($commandbar);


// Some slots already exist - prepare the table of slots.
if ($slots) {

    $slotman = new scheduler_slot_manager($scheduler, $actionurl);
    $slotman->showteacher = ($subpage == 'allappointments');

    foreach ($slots as $slot) {

        $editable = $permissions->can_edit_slot($slot);

        $studlist = new scheduler_student_list($slotman->scheduler);
        $studlist->expandable = false;
        $studlist->expanded = true;
        $studlist->editable = $editable;
        $studlist->linkappointment = true;
        $studlist->checkboxname = 'seen[]';
        $studlist->buttontext = get_string('saveseen', 'scheduler');
        $studlist->actionurl = new moodle_url($actionurl, array('what' => 'saveseen', 'slotid' => $slot->id, 'istutor' => 1));
        foreach ($slot->get_appointments() as $app) {
            $studlist->add_student($app, false, $app->is_attended(), true, $scheduler->uses_studentdata(),
                                   $permissions->can_edit_attended($app));
        }

        $slotman->add_slot($slot, $studlist, $editable);
    }

    echo $output->render($slotman);

    if ($sqlcount > $pagesize) {
        echo $output->paging_bar($sqlcount, $offset, $pagesize, $actionurl, 'offset');
    }
}

$groupfilter = ($subpage == 'myappointments') ? $groupsthatcanseeme : $groupsicurrentlysee;
$maxlistsize = get_config('mod_scheduler', 'maxstudentlistsize');
$students = array();
$reminderstudents = array();
if ($groupfilter === '') {
    $students = $scheduler->get_students_for_scheduling('', $maxlistsize);
    if ($scheduler->allows_unlimited_bookings()) {
        $reminderstudents  = $scheduler->get_students_for_scheduling('', $maxlistsize, true);
    } else {
        $reminderstudents = $students;
    }
} else if (count($groupfilter) > 0) {
    $students = $scheduler->get_students_for_scheduling(array_keys($groupfilter), $maxlistsize);
    if ($scheduler->allows_unlimited_bookings()) {
        $reminderstudents = $scheduler->get_students_for_scheduling(array_keys($groupfilter), $maxlistsize, true);
    } else {
        $reminderstudents = $students;
    }
}

echo $output->footer();
