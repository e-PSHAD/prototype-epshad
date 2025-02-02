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

namespace theme_padplus\output\core;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/local/padplusextensions/lib.php');

use action_link,
    context_course,
    core_course_category,
    cm_info,
    core_course_list_element,
    core_tag_tag,
    coursecat_helper,
    html_writer,
    lang_string,
    moodle_url,
    stdClass;

/*** PADPLUS
 * We override various render methods to customize HTML structure.
 *
 * As a reminder, here if the overall render hierarchy for category page:
 * (starred *methods are overridden in this renderer.)
 * (plussed +methods are new in this renderer.)
 *
 * - *course_category: display particular course category, either top one or a subcategory
 *   - *coursecat_tree: display a tree of subcategories and courses in the given category
 *     - coursecat_category_content: display the subcategories and courses in the given category
 *       - coursecat_subcategories: renders the list of subcategories in a category
 *         - *coursecat_category: display a course category as a part of a tree
 *       - coursecat_courses: renders the list of courses
 *         - *coursecat_coursebox: displays one course in the list of courses
 *           - +coursebox_image: displays course image for the coursebox
 *           - +coursecat_coursebox_details: display all course details
 *             - +course_tags: display course tags
 *             - course_name
 *             - course_summary
 *             - *course_contacts: display organizers
 *             - +coursebox_enrolled_students: return the number of students enrolled in the course
 *
 * Render hierarchy on workshop self-registration page:
 * - *course_info_box
 *   - +course_tags
 *   - course_summary
 *   - *course_contacts
 */
class course_renderer extends \core_course_renderer {

    /**
     * Renders HTML to display particular course category - list of it's subcategories and courses
     *
     * Invoked from /course/index.php
     *
     * @param int|stdClass|core_course_category $category
     */
    public function course_category($category) {
        global $CFG;
        $usertop = core_course_category::user_top();
        if (empty($category)) {
            $coursecat = $usertop;
        } else if (is_object($category) && $category instanceof core_course_category) {
            $coursecat = $category;
        } else {
            $coursecat = core_course_category::get(is_object($category) ? $category->id : $category);
        }
        $site = get_site();
        $output = '';

        /*** PADPLUS: only display category management button for admins/managers, not course creators. */
        if ($coursecat->has_manage_capability()) {
            // Add 'Manage' button if user has permissions to edit this category.
            $managebutton = $this->single_button(new moodle_url('/course/management.php',
                array('categoryid' => $coursecat->id)), get_string('managecourses'), 'get');
            $this->page->set_button($managebutton);
        }

        if (core_course_category::is_simple_site()) {
            // There is only one category in the system, do not display link to it.
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");
        } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
            $strcategories = get_string('categories');
            $this->page->set_title("$site->shortname: $strcategories");
        } else {
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");

            /*** PADPLUS: remove category selector */
        }

        // Print current category description.
        $chelper = new coursecat_helper();
        if ($description = $chelper->get_category_formatted_description($coursecat)) {
            $output .= $this->box($description, array('class' => 'generalbox info'));
        }

        // Prepare parameters for courses and categories lists in the tree.
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_AUTO)
            ->set_attributes(array('class' => 'category-browse category-browse-'.$coursecat->id));

        $coursedisplayoptions = array();
        $catdisplayoptions = array();
        $browse = optional_param('browse', null, PARAM_ALPHA);
        $perpage = optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $baseurl = new moodle_url('/course/index.php');
        if ($coursecat->id) {
            $baseurl->param('categoryid', $coursecat->id);
        }
        if ($perpage != $CFG->coursesperpage) {
            $baseurl->param('perpage', $perpage);
        }
        $coursedisplayoptions['limit'] = $perpage;
        $catdisplayoptions['limit'] = $perpage;
        if ($browse === 'courses' || !$coursecat->get_children_count()) {
            $coursedisplayoptions['offset'] = $page * $perpage;
            $coursedisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => 'courses'));
            $catdisplayoptions['nodisplay'] = true;
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories'));
            $catdisplayoptions['viewmoretext'] = new lang_string('viewallsubcategories');
        } else if ($browse === 'categories' || !$coursecat->get_courses_count()) {
            $coursedisplayoptions['nodisplay'] = true;
            $catdisplayoptions['offset'] = $page * $perpage;
            $catdisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => 'categories'));
            $coursedisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'courses'));
            $coursedisplayoptions['viewmoretext'] = new lang_string('viewallcourses');
        } else {
            // We have a category that has both subcategories and courses, display pagination separately.
            $coursedisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'courses', 'page' => 1));
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories', 'page' => 1));
        }
        $chelper->set_courses_display_options($coursedisplayoptions)->set_categories_display_options($catdisplayoptions);

        /*** PADPLUS: category management button. */
        if ($coursecat->has_manage_capability()) {
            $output .= html_writer::start_div('', ['class' => 'category-page-settings-container']);
            $output .= $this->region_main_settings_menu();
            $output .= html_writer::end_div();
        }
        /*** PADPLUS END */

        // Display course category tree.
        $output .= $this->coursecat_tree($chelper, $coursecat);

        // Add action buttons.
        $output .= $this->container_start('buttons category-page-btns-container');
        if ($coursecat->is_uservisible()) {
            $context = get_category_or_system_context($coursecat->id);
            /*** PADPLUS: only display 'add course' button when there is no subcategory. */
            $categoryisleaf = $coursecat->get_children_count() === 0;
            if ($categoryisleaf && has_capability('moodle/course:create', $context)) {
                // Print link to create a new course, for the 1st available category.
                if ($coursecat->id) {
                    $url = new moodle_url('/course/edit.php', array('category' => $coursecat->id, 'returnto' => 'category'));
                } else {
                    $url = new moodle_url('/course/edit.php',
                        array('category' => $CFG->defaultrequestcategory, 'returnto' => 'topcat'));
                }
                /*** PADPLUS: customize 'add course' button. */
                $text = category_belongs_to_workshop($coursecat, $this->page->theme) ?
                    get_string('addnewworkshop', 'theme_padplus') : get_string('addnewcourse');
                $attributes = ['class' => 'btn btn-primary btn-add-course'];
                $link = new action_link($url, $text, null, $attributes);
                $output .= $this->render($link);
                /*** PADPLUS END */
            }
            ob_start();
            print_course_request_buttons($context);
            $output .= ob_get_contents();
            ob_end_clean();
        }
        $output .= $this->container_end();

        return $output;
    }

    /**
     * Returns HTML to display a tree of subcategories and courses in the given category
     *
     * @param coursecat_helper $chelper various display options
     * @param core_course_category $coursecat top category (this category's name and description will NOT be added to the tree)
     * @return string
     */
    protected function coursecat_tree(coursecat_helper $chelper, $coursecat) {
        // Reset the category expanded flag for this course category tree first.
        $this->categoryexpandedonload = false;
        $categorycontent = $this->coursecat_category_content($chelper, $coursecat, 0);
        if (empty($categorycontent)) {
            return '';
        }

        // Start content generation.
        $content = '';
        $attributes = $chelper->get_and_erase_attributes('course_category_tree clearfix');
        $content .= html_writer::start_tag('div', $attributes);

        /*** PADPLUS: remove collapsible button. */

        $content .= html_writer::tag('div', $categorycontent, array('class' => 'content'));

        $content .= html_writer::end_tag('div');

        return $content;
    }

    /*** PADPLUS
     * Returns HTML to display a course category as a part of a tree
     *
     * This is a trimmed down version of the original coursecat_category function, since we do not display subcategories here.
     *
     * @param coursecat_helper $chelper various display options
     * @param core_course_category $coursecat
     * @param int $depth depth of this category in the current tree
     * @return string
     */
    protected function coursecat_category(coursecat_helper $chelper, $coursecat, $depth) {
        $categoryname = $coursecat->get_formatted_name();
        $childrencount = $coursecat->get_courses_count() + $coursecat->get_children_count();

        $categoryblock = '';
        $categoryblock .= html_writer::start_tag('h5', array('class' => 'category-title'));
        $categoryblock .= $categoryname;
        $categoryblock .= ' (' . $childrencount . ')';
        $categoryblock .= html_writer::end_tag('h5');

        $content = html_writer::link(new moodle_url('/course/index.php',
                array('categoryid' => $coursecat->id)),
                $categoryblock,
                array('class' => 'category-item'));
        return $content;
    }
    /*** PADPLUS END */

    /**
     * Displays one course in the list of courses.
     *
     * @param coursecat_helper $chelper various display options
     * @param core_course_list_element|stdClass $course
     * @param string $additionalclasses additional classes to add to the main <div> tag (usually
     *    depend on the course position in list - first/last/even/odd)
     * @return string
     */
    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        if (!isset($this->strings->summary)) {
            $this->strings->summary = get_string('summary');
        }
        if ($chelper->get_show_courses() <= self::COURSECAT_SHOW_COURSES_COUNT) {
            return '';
        }
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }
        $content = '';
        $classes = trim('coursebox clearfix '. $additionalclasses);
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            $classes .= ' collapsed';
        }

        // Coursebox.
        $content .= html_writer::start_tag('div', array(
            'class' => $classes,
            'data-courseid' => $course->id,
            'data-type' => self::COURSECAT_TYPE_COURSE,
        ));

        /*** PADPLUS: course image & course/workshop info. */
        $content .= html_writer::start_tag('div', array('class' => 'coursecat-image'));
        $content .= $this->coursebox_image($course);
        $content .= html_writer::end_tag('div');

        // The content of the coursebox changes depending on whether it is a course or a workshop.
        if (course_is_workshop($course, $this->page->theme)) {
            $rolelabel = get_string('workshop-teacher', 'theme_padplus');
            $contactbox = $this->course_contacts($course, $rolelabel); // Changed wording for teacher role when it's a workshop.
            $studentsbox = ''; // Do not show enrolled student number for workshop.
            $buttonlabel = get_string('btn-workshopbox', 'theme_padplus');
            $boxclassname = 'workshopbox';
        } else {
            $contactbox = $this->course_contacts($course);
            $studentsbox = $this->coursebox_enrolled_students($course);
            $buttonlabel = get_string('btn-coursebox', 'theme_padplus');
            $boxclassname = '';
        }
        $content .= $this->coursecat_coursebox_details($course, $chelper, $contactbox, $studentsbox, $buttonlabel, $boxclassname);
        /*** PADPLUS END */

        $content .= html_writer::end_tag('div');

        $content .= html_writer::end_tag('div'); // Coursebox.
        return $content;
    }


    /*** PADPLUS private
     * Display all coursebox info (either a sequence or workshop) in category page.
     */
    private function coursecat_coursebox_details($course, $chelper, $contactbox, $studentsbox, $buttonlabel, $boxclassname) {
        $output = html_writer::start_tag('div', array('class' => "info $boxclassname"));
        $output .= html_writer::start_tag('div');
        $output .= $this->course_tags($course);
        $output .= $this->course_name($chelper, $course);
        $output .= $this->course_summary($chelper, $course);
        $output .= $contactbox;
        $output .= $studentsbox;
        $output .= html_writer::end_tag('div');
        $output .= html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            $buttonlabel,
            array('class' => $course->visible ? 'btn btn-secondary' : 'btn btn-secondary disabled'));
        return $output;
    }

    /*** PADPLUS private
     * Display course image or default one.
     */
    private function coursebox_image($course) {
        $image = \cache::make('core', 'course_image')->get($course->id);
        if (is_null($image)) {
            $image = $this->get_generated_image_for_id($course->id);
        }
        return html_writer::tag('div',
               html_writer::empty_tag('img', ['src' => $image]),
               ['class' => 'courseimage']);
    }

    /*** PADPLUS private
     * Display number of students enrolled in the course.
     */
    private function coursebox_enrolled_students($course) {
        $context = context_course::instance($course->id);
        $output = html_writer::start_tag('div', array('class' => 'participants'));
        $output .= get_string('participants-enrolled', 'theme_padplus') . ' : ';
        $output .= html_writer::start_tag('span');
        $enrolledstudents = count_enrolled_users($context, 'mod/assign:submit');
        if ($enrolledstudents > 0) {
            $output .= $enrolledstudents;
        } else {
            $output .= get_string('no-participants-enrolled', 'theme_padplus') . '.';
        }
        $output .= html_writer::end_tag('span');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /*** PADPLUS private
     * Display course tags.
     */
    private function course_tags($course) {
        $tags = core_tag_tag::get_item_tags('core', 'course', $course->id);
        if (empty($tags)) {
            return '';
        }
        $output = html_writer::start_tag('div', array('class' => 'd-flex flex-wrap coursetags'));
        foreach ($tags as $tag) {
            $output .= html_writer::tag('div', $tag->rawname, array('class' => 'badge badge-pad badge-pad-info'));
        }
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Returns HTML to display course contacts.
     *
     * @param core_course_list_element $course
     * @return string
     */
    protected function course_contacts(core_course_list_element $course, string $rolelabel = null) {
        $content = '';
        if ($course->has_course_contacts()) {
            $content .= html_writer::start_tag('ul', ['class' => 'teachers']);
            foreach ($course->get_course_contacts() as $coursecontact) {
                /*** PADPLUS: get default role label or override with given one. */
                if (is_null($rolelabel)) {
                    $rolenames = array_map(function ($role) {
                        return $role->displayname;
                    }, $coursecontact['roles']);
                } else {
                    $rolenames = array($rolelabel);
                }
                $name = implode(", ", $rolenames).' : '.
                    html_writer::link(new moodle_url('/user/view.php',
                        ['id' => $coursecontact['user']->id, 'course' => SITEID]),
                        $coursecontact['username']);
                $content .= html_writer::tag('li', $name);
                /*** PADPLUS END */
            }
            $content .= html_writer::end_tag('ul');
        }
        return $content;
    }

    /**
     * Display workshop info on the self-enrolment page.
     *
     * @param stdClass $course
     * @return string
     */
    public function course_info_box(stdClass $course) {
        $content = '';
        $content .= $this->output->box_start('generalbox info');
        $chelper = new coursecat_helper();

        /*** PADPLUS: returns tags, summary & organizers */
        $rolelabel = get_string('workshop-teacher', 'theme_padplus');
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }
        $content .= $this->course_tags($course);
        $content .= $this->course_summary($chelper, $course);
        $content .= $this->course_contacts($course, $rolelabel);
        /*** PADPLUS END */

        $content .= $this->output->box_end();
        return $content;
    }

    /**
     * Renders HTML to display one course module in a course section
     *
     * This includes link, content, availability, completion info and additional information
     * that module type wants to display (i.e. number of unread forum posts)
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm_name()}
     * {@link core_course_renderer::course_section_cm_text()}
     * {@link core_course_renderer::course_section_cm_availability()}
     * {@link course_get_cm_edit_actions()}
     * {@link core_course_renderer::course_section_cm_edit_actions()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        global $USER;

        $output = '';
        // We return empty string (because course module will not be displayed at all)
        // if:
        // 1) The activity is not visible to users
        // and
        // 2) The 'availableinfo' is empty, i.e. the activity was
        // hidden in a way that leaves no info, such as using the
        // eye icon.
        if (!$mod->is_visible_on_course_page()) {
            return $output;
        }

        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        if ($this->page->user_is_editing()) {
            $output .= course_get_cm_move($mod, $sectionreturn);
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer w-100'));

        // This div is used to indent the content.
        $output .= html_writer::div('', $indentclasses);

        // Start a wrapper for the actual content to keep the indentation consistent.
        $output .= html_writer::start_tag('div');

        // Display the link to the module (or do nothing if module has no url).
        $cmname = $this->course_section_cm_name($mod, $displayoptions);

        if (!empty($cmname)) {
            // Start the div for the activity title, excluding the edit icons.
            $output .= html_writer::start_tag('div', array('class' => 'activityinstance'));
            $output .= $cmname;

            // Module can put text after the link (e.g. forum unread).
            $output .= $mod->afterlink;

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        // If there is content but NO link (eg label), then display the
        // content here (BEFORE any icons). In this case cons must be
        // displayed after the content so that it makes more sense visually
        // and for accessibility reasons, e.g. if you have a one-line label
        // it should work similarly (at least in terms of ordering) to an
        // activity.
        $contentpart = $this->course_section_cm_text($mod, $displayoptions);
        $url = $mod->url;
        if (empty($url)) {
            $output .= $contentpart;
        }

        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $modicons .= ' '. $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $modicons .= $mod->afterediticons;
        }

        if (!empty($modicons)) {
            $output .= html_writer::div($modicons, 'actions');
        }

        // Fetch completion details.
        $showcompletionconditions = $course->showcompletionconditions == COMPLETION_SHOW_CONDITIONS;
        $completiondetails = \core_completion\cm_completion_details::get_instance($mod, $USER->id, $showcompletionconditions);
        $ismanualcompletion = $completiondetails->has_completion() && !$completiondetails->is_automatic();

        // Fetch activity dates.
        $activitydates = [];
        if ($course->showactivitydates) {
            $activitydates = \core\activity_dates::get_dates_for_module($mod, $USER->id);
        }

        /*** PADPLUS: Place the description before the activity information on the section box */
        // If there is content AND a link, then display the content here
        // (AFTER any icons). Otherwise it was displayed before.
        if (!empty($url)) {
            $output .= $contentpart;
        }
        /*** PADPLUS END */

        // Show the activity information if:
        // - The course's showcompletionconditions setting is enabled; or
        // - The activity tracks completion manually; or
        // - There are activity dates to be shown.
        if ($showcompletionconditions || $ismanualcompletion || $activitydates) {
            $output .= $this->output->activity_information($mod, $completiondetails, $activitydates);
        }

        // Show availability info (if module is not available).
        $output .= $this->course_section_cm_availability($mod, $displayoptions);

        /*** PADPLUS: Description located above */

        $output .= html_writer::end_tag('div'); // $indentclasses

        // End of indentation div.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Renders the activity navigation.
     *
     * Defer to template.
     *
     * @param \core_course\output\activity_navigation $page
     * @return string html for the page
     */
    public function render_activity_navigation(\core_course\output\activity_navigation $page) {
        $data = $page->export_for_template($this->output);

        /*** PADPLUS: custom activity navigation. */
        if (isset($data->prevlink)) {
            $data->prevlink->text = get_string('previous-activity', 'theme_padplus');
        }

        if (isset($data->nextlink)) {
            $data->nextlink->text = get_string('next-activity', 'theme_padplus');
        }

        $text = get_string('course-homepage', 'theme_padplus');
        $url = new moodle_url('/course/view.php', [ 'id' => $this->page->course->id ]);
        $courselink = new action_link($url, $text, null, null);
        $data->activitylist = $courselink;
        /*** PADPLUS END */

        return $this->output->render_from_template('core_course/activity_navigation', $data);
    }
}
