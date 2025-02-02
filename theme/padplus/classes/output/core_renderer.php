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

namespace theme_padplus\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/padplusvideocall/lib.php');
require_once($CFG->dirroot . '/local/padplusextensions/lib.php');

use action_link,
    action_menu,
    action_menu_filler,
    action_menu_link_secondary,
    core_text,
    context_header,
    context_system,
    html_writer,
    moodle_url,
    navigation_node,
    pix_icon;

class core_renderer extends \core_renderer {

    /*** PADPLUS: set main region for accessibility */
    public function main_content() {
        return '<main role="main">'.$this->unique_main_content_token.'</main>';
    }
    /*** PADPLUS END */

    /*** PADPLUS: set custom label for accessibility */
    public function search_box($id = false) {
        global $CFG;

        if (empty($CFG->enableglobalsearch) || !has_capability('moodle/search:query', context_system::instance())) {
            return '';
        }

        $data = [
            'action' => new moodle_url('/search/index.php'),
            'hiddenfields' => (object) ['name' => 'context', 'value' => $this->page->context->id],
            'inputname' => 'q',
            'searchstring' => get_string('global-search', 'theme_padplus'),
            ];
        return $this->render_from_template('core/search_input_navbar', $data);
    }
    /*** PADPLUS END */

    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        if (is_null($user)) {
            $user = $USER;
        }

        // Note: this behaviour is intended to match that of core_renderer::login_info,
        // but should not be considered to be good practice; layout options are
        // intended to be theme-specific. Please don't copy this snippet anywhere else.
        if (is_null($withlinks)) {
            $withlinks = empty($this->page->layout_options['nologinlinks']);
        }

        // Add a class for when $withlinks is false.
        $usermenuclasses = 'usermenu';
        if (!$withlinks) {
            $usermenuclasses .= ' withoutlinks';
        }

        $returnstr = "";

        // If during initial install, return the empty return string.
        if (during_initial_install()) {
            return $returnstr;
        }

        $loginpage = $this->is_login_page();
        $loginurl = get_login_url();
        // If not logged in, show the typical not-logged-in string.
        if (!isloggedin()) {
            $returnstr = get_string('loggedinnot', 'moodle');
            if (!$loginpage) {
                $returnstr .= " (<a href=\"$loginurl\">" . get_string('login') . '</a>)';
            }
            return html_writer::div(
                html_writer::span(
                    $returnstr,
                    'login'
                ),
                $usermenuclasses
            );

        }

        // If logged in as a guest user, show a string to that effect.
        if (isguestuser()) {
            $returnstr = get_string('loggedinasguest');
            if (!$loginpage && $withlinks) {
                $returnstr .= " (<a href=\"$loginurl\">".get_string('login').'</a>)';
            }

            return html_writer::div(
                html_writer::span(
                    $returnstr,
                    'login'
                ),
                $usermenuclasses
            );
        }

        // Get some navigation opts.
        $opts = user_get_user_navigation_info($user, $this->page);

        $avatarclasses = "avatars";
        $avatarcontents = html_writer::span($opts->metadata['useravatar'], 'avatar current');
        $usertextcontents = $opts->metadata['userfullname'];

        // Other user.
        if (!empty($opts->metadata['asotheruser'])) {
            $avatarcontents .= html_writer::span(
                $opts->metadata['realuseravatar'],
                'avatar realuser'
            );
            $usertextcontents = $opts->metadata['realuserfullname'];
            $usertextcontents .= html_writer::tag(
                'span',
                get_string(
                    'loggedinas',
                    'moodle',
                    html_writer::span(
                        $opts->metadata['userfullname'],
                        'value'
                    )
                ),
                array('class' => 'meta viewingas')
            );
        }

        // Role.
        if (!empty($opts->metadata['asotherrole'])) {
            $role = core_text::strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['rolename'])));
            $usertextcontents .= html_writer::span(
                $opts->metadata['rolename'],
                'meta role role-' . $role
            );
        }

        // User login failures.
        if (!empty($opts->metadata['userloginfail'])) {
            $usertextcontents .= html_writer::span(
                $opts->metadata['userloginfail'],
                'meta loginfailures'
            );
        }

        // MNet.
        if (!empty($opts->metadata['asmnetuser'])) {
            $mnet = strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['mnetidprovidername'])));
            $usertextcontents .= html_writer::span(
                $opts->metadata['mnetidprovidername'],
                'meta mnet mnet-' . $mnet
            );
        }

        /*** PADPLUS: switch avatar and username in user menu */
        $returnstr .= html_writer::span(
            html_writer::span($avatarcontents, $avatarclasses) .
            html_writer::span($usertextcontents, 'usertext mr-1'),
            'userbutton'
        );
        /*** PADPLUS END */

        // Create a divider (well, a filler).
        $divider = new action_menu_filler();
        $divider->primary = false;

        $am = new action_menu();
        $am->set_menu_trigger(
            $returnstr
        );
        $am->set_action_label(get_string('usermenu'));
        $am->set_alignment(action_menu::TR, action_menu::BR);
        $am->set_nowrap_on_items();
        if ($withlinks) {
            $navitemcount = count($opts->navitems);
            $idx = 0;
            foreach ($opts->navitems as $key => $value) {

                switch ($value->itemtype) {
                    case 'divider':
                        // If the nav item is a divider, add one and skip link processing.
                        $am->add($divider);
                        break;

                    case 'invalid':
                        // Silently skip invalid entries (should we post a notification?).
                        break;

                    case 'link':
                        // Process this as a link item.
                        $pix = null;
                        if (isset($value->pix) && !empty($value->pix)) {
                            $pix = new pix_icon($value->pix, '', null, array('class' => 'iconsmall'));
                        } else if (isset($value->imgsrc) && !empty($value->imgsrc)) {
                            $value->title = html_writer::img(
                                $value->imgsrc,
                                $value->title,
                                array('class' => 'iconsmall')
                            ) . $value->title;
                        }

                        $al = new action_menu_link_secondary(
                            $value->url,
                            $pix,
                            $value->title,
                            array('class' => 'icon')
                        );
                        if (!empty($value->titleidentifier)) {
                            $al->attributes['data-title'] = $value->titleidentifier;
                        }
                        $am->add($al);
                        break;
                }

                $idx++;

                // Add dividers after the first item and before the last item.
                if ($idx == 1 || $idx == $navitemcount - 1) {
                    $am->add($divider);
                }
            }
        }

        return html_writer::div(
            $this->render($am),
            $usermenuclasses
        );
    }

    private function is_dashboard_page() {
        $pagepath = $this->page->url->get_path();
        return $pagepath === '/my/index.php';
    }

    private function is_section_page() {
        $pagepath = $this->page->url->get_path();
        $hassectionparam = $this->page->url->get_param('section') != null;
        return $pagepath === '/course/view.php' && $hassectionparam;
    }

    private function is_activity_page() {
        $pagepath = $this->page->url->get_path();
        if ($pagepath === '/mod/forum/user.php') {
            // Special case: forum/user.php is not an activity but a report page.
            // It reports all posts and messages made by user in forums.
            // It can be accessed through user profile or course profile (which limits scope to course forums).
            return false;
        }

        return strpos($pagepath, '/mod/') === 0;
    }

    /*** PADPLUS: override dashboard title for consistency and custom heading */
    public function page_title() {
        global $SITE;
        if ($this->is_dashboard_page()) {
            $strmymoodle = get_string('myhome');
            return "$SITE->shortname: $strmymoodle";
        }
        return $this->page->title;
    }

    /*** PADPLUS: override context header so that we can customize main page header before proceeding */
    public function context_header($headerinfo = null, $headinglevel = 1) {
        global $USER;
        if ($this->is_dashboard_page()) {
            $this->page->set_heading(get_string('myhome-welcome', 'theme_padplus', $USER->firstname));

        } else if ($this->is_section_page()) {
            // Use section name as page header on section page, IFF it has a name.
            $sectionno = optional_param('section', 0, PARAM_INT);
            $section = course_get_format($this->page->course)->get_section($sectionno);
            if ($section->name != null) {
                $this->page->set_heading($section->name);
            }

        } else if ($this->is_activity_page()) {
            // Also use section name as page header on activity page, IFF it has a name.
            // Some section (such as initial section in single activity course) may not have a name,
            // in this case we should resort to default course name, which is ok.
            $modinfo = get_fast_modinfo($this->page->course->id);
            $cminfo = $modinfo->get_cm($this->page->cm->id);
            $section = course_get_format($this->page->course)->get_section($cminfo->sectionnum);
            if ($section->name != null) {
                $this->page->set_heading($section->name);
            }
        }

        return parent::context_header($headerinfo, $headinglevel);
    }

    /*** PADPLUS: override render context header so that we can customize action buttons.
     * Sadly this is not possible in context_header since it directly calls the renderer.
     * So we intercept the renderer call to override some more parameters.
     *
     * Renders the header bar.
     *
     * @param context_header $contextheader Header bar object.
     * @return string HTML for the header bar.
     */
    protected function render_context_header(context_header $contextheader) {
        if ($this->page->theme->settings->videocallinprofile) {
            // Retrieve top category where user has the createvideocall capability, if any.
            $categorycontext = get_top_category_context_with_capability('block/padplusvideocall:createvideocall');

            // Let's take a shortcut!
            // If there is a 'toggle contact' button, it means we are on the profile page for another user,
            // either site profile or course profile (beware, the last one does not have a user context).
            // Then we also add the videocall button if user has the capability context.
            if ($categorycontext && isset($contextheader->additionalbuttons['togglecontact'])) {
                // Both site profile (user/profile.php) and course profile (user/view.php) have user id in param.
                // This will be used to notify the viewer.
                $viewerid = $this->page->url->get_param('id');
                $buttonid = 'video-call-user-button';
                $videocall = array(
                    'videocall' => array(
                        'buttontype' => 'videocall',
                        'title' => get_string('callfromprofile', 'block_padplusvideocall'),
                        'url' => new moodle_url(get_videocall_join_url(generate_videocall_data(), [$viewerid])),
                        'formattedimage' => 'i/bullhorn',
                        'linkattributes' => array(
                            'id' => $buttonid,
                            'role' => 'button',
                            'class' => 'btn'
                        ),
                        'page' => $this->page
                    )
                );
                $this->page->requires->js_call_amd(
                    'block_padplusvideocall/main',
                    'handleVideoCallRequest',
                    array($buttonid)
                );

                $contextheader->additionalbuttons = $contextheader->additionalbuttons + $videocall;
            }
        }

        return parent::render_context_header($contextheader);
    }

     /*** PADPLUS: override settings menu on home, course & profile page */
    public function context_header_settings_menu() {
        $context = $this->page->context;

        $items = $this->page->navbar->get_items();
        $currentnode = end($items);

        $showcoursemenu = false;
        $showfrontpagemenu = false;
        $showusermenu = false;

        // We are on the course home page.
        if (($context->contextlevel == CONTEXT_COURSE) &&
                !empty($currentnode) &&
                ($currentnode->type == navigation_node::TYPE_COURSE || $currentnode->type == navigation_node::TYPE_SECTION)) {
            $showcoursemenu = true;
        }

        $courseformat = course_get_format($this->page->course);
        // This is a single activity course format, always show the course menu on the activity main page.
        if ($context->contextlevel == CONTEXT_MODULE &&
                !$courseformat->has_view_page()) {

            $this->page->navigation->initialise();
            $activenode = $this->page->navigation->find_active_node();
            // If the settings menu has been forced then show the menu.
            if ($this->page->is_settings_menu_forced()) {
                $showcoursemenu = true;
            } else if (!empty($activenode) && ($activenode->type == navigation_node::TYPE_ACTIVITY ||
                            $activenode->type == navigation_node::TYPE_RESOURCE)) {

                // We only want to show the menu on the first page of the activity. This means
                // the breadcrumb has no additional nodes.
                if ($currentnode && ($currentnode->key == $activenode->key && $currentnode->type == $activenode->type)) {
                    $showcoursemenu = true;
                }
            }
        }

        // This is the site front page.
        if ($context->contextlevel == CONTEXT_COURSE &&
                !empty($currentnode) &&
                $currentnode->key === 'home') {
            $showfrontpagemenu = true;
        }

        // This is the user profile page.
        if ($context->contextlevel == CONTEXT_USER &&
                !empty($currentnode) &&
                ($currentnode->key === 'myprofile')) {
            $showusermenu = true;
        }

        $attributes = ['class' => 'btn btn-secondary'];

        if ($showfrontpagemenu) {
            $settingsnode = $this->page->settingsnav->find('frontpage', navigation_node::TYPE_SETTING);
            if ($settingsnode) {
                // Show parameters link if user has access to settings on front page.
                $text = get_string('frontpagesettings');
                $url = new moodle_url('/course/admin.php', array('courseid' => $this->page->course->id));
            }
        } else if ($showcoursemenu) {
            $settingsnode = $this->page->settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
            if ($settingsnode) {
                $unenrolnode = $settingsnode->get('unenrolself');
                if ($unenrolnode && $settingsnode->children->count() == 1) {
                    // If user has a unique 'unenrol' node, this is a student: show the unenrol link instead of the parameters link.
                    $text = get_string('unenrolme', 'theme_padplus');
                    $attributes = ['class' => 'btn btn-primary'];
                    $url = $unenrolnode->action;
                } else {
                    // Show parameters link if user has access to settings on course page.
                    $text = get_string('courseadministration');
                    $url = new moodle_url('/course/admin.php', array('courseid' => $this->page->course->id));
                }
            }
        } else if ($showusermenu) {
            // Always show parameters link on user page.
            $text = get_string('preferences');
            $url = new moodle_url('/user/preferences.php');
        }

        if (isset($url)) {
            $link = new action_link($url, $text, null, $attributes);
            return $this->render($link);
        } else {
            return '';
        }
    }
    /*** PADPLUS END */

    /**
     * This is an optional menu that can be added to a layout by a theme. It contains the
     * menu for the most specific thing from the settings block. E.g. Module administration.
     *
     * @return string
     */
    public function region_main_settings_menu() {
        $context = $this->page->context;
        $menu = new action_menu();

        if ($context->contextlevel == CONTEXT_MODULE) {

            $this->page->navigation->initialise();
            $node = $this->page->navigation->find_active_node();
            $buildmenu = false;
            // If the settings menu has been forced then show the menu.
            if ($this->page->is_settings_menu_forced()) {
                $buildmenu = true;
            } else if (!empty($node) && ($node->type == navigation_node::TYPE_ACTIVITY ||
                            $node->type == navigation_node::TYPE_RESOURCE)) {

                $items = $this->page->navbar->get_items();
                $navbarnode = end($items);
                // We only want to show the menu on the first page of the activity. This means
                // the breadcrumb has no additional nodes.
                if ($navbarnode && ($navbarnode->key === $node->key && $navbarnode->type == $node->type)) {
                    $buildmenu = true;
                }
            }
            if ($buildmenu) {
                // Get the course admin node from the settings navigation.
                $node = $this->page->settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }
            }

        } else if ($context->contextlevel == CONTEXT_COURSECAT) {
            // For course category context, show category settings menu, if we're on the course category page.
            if ($this->page->pagetype === 'course-index-category') {
                $node = $this->page->settingsnav->find('categorysettings', navigation_node::TYPE_CONTAINER);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }
            }

        } else {
            $items = $this->page->navbar->get_items();
            $navbarnode = end($items);

            if ($navbarnode && ($navbarnode->key === 'participants')) {
                $node = $this->page->settingsnav->find('users', navigation_node::TYPE_CONTAINER);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }

            }
        }
        /*** PADPLUS: set generic but explicit label for dropdown trigger, to replace the cogwheel icon. */
        $menu->menutrigger = get_string('actions-dropdown', 'theme_padplus');
        return $this->render($menu);
    }

    /**
     * Returns HTML to display a "Turn editing on/off" button in a form.
     *
     * @param moodle_url $url The URL + params to send through when clicking the button
     * @return string HTML the button
     */
    public function edit_button(moodle_url $url) {

        $url->param('sesskey', sesskey());
        if ($this->page->user_is_editing()) {
            $url->param('edit', 'off');
            $editstring = get_string('turneditingoff');
            $options = ['class' => 'turneditingoff'];
        } else {
            $url->param('edit', 'on');
            $editstring = get_string('turneditingon');
            $options = ['class' => 'turneditingon'];
        }

        return $this->single_button($url, $editstring, 'post', $options);
    }
}
