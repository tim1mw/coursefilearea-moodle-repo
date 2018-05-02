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

/*
 * repository_coursefilearea class is used to browse course files. This is a composite of the coursefiles and
 * filesystem repository code with some new code added.
 *
 * @package    repository_coursefilearea
 * @copyright 2011 Tim Williams <tmw@autotrain.org> (Modified from code originally written by Dongsheng Cai <dongsheng@moodle.com>)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * Core repository class
 * @copyright 2011 Tim Williams <tmw@autotrain.org> (Modified from code originally written by Dongsheng Cai <dongsheng@moodle.com>)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_coursefilearea extends repository {
    /*
     * Get the option names.
     * @return Array of option names.
     */
    public static function get_type_option_names() {
        return array('allowinternal', 'pluginname');
    }

    /*
    * Gets the current course object
    * @return The course
    */
    private function get_course() {
        global $CFG;
        // For Moodle 2.2 to 2.2.3+.
        if ($CFG->version > 2011120500 && $CFG->version < 2011120503.06) {
            global $USER, $DB;
            $cid = array_keys($USER->currentcourseaccess);
            $cid = $cid[0];
            return $DB->get_record("course", array("id" => $cid));
        }
        global $COURSE;
        return $COURSE;
    }

    /*
     * Setup repistory form.
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform,  $classname = 'repository') {
        repository::type_config_form($mform);
        $allowinternal = get_config('coursefilearea', 'allowinternal');
        if (empty($allowinternal)) {
            $allowinternal = 0;
        }
        $desc = '<p>' . get_string('allowinternaldesc', 'repository_coursefilearea') . '</p>';
        $desc .= '<p>' . get_string('redirectpatchnotdetected', 'repository_coursefilearea') . '</p>';
        $mform->addElement('checkbox', 'allowinternal', get_string('allowinternaltitle', 'repository_coursefilearea'), $desc);
    }

    /**
     * Course file area plugin doesn't require login, so list all files
     * @return mixed
     */
    public function print_login() {
        return $this->get_listing();
    }

    /*
     * Given a path, and perhaps a search, get a list of files.
     *
     * See details on {@link http://docs.moodle.org/dev/Repository_plugins}
     *
     * @param string $path identifier for current path
     * @param string $page the page number of file list
     * @return array list of files including meta information as specified by parent.
     */
    public function get_listing($path = '', $page = '') {
        global $CFG, $USER, $OUTPUT;
        $courseid = $this->get_course()->id;
        $list = array();
        $list['list'] = array();
        // Process breacrumb trail.
        $list['path'] = array(array('name' => 'Root', 'path' => ''));
        $trail = '';
        if (!empty($path)) {
            $parts = explode('/', $path);
            if (count($parts) > 1) {
                foreach ($parts as $part) {
                    if (!empty($part)) {
                        $trail .= ('/' . $part);
                        $list['path'][] = array('name' => $part, 'path' => $trail);
                    }
                }
            } else {
                $list['path'][] = array('name' => $path, 'path' => $path);
            }
        }
        $list['manage'] = false;
        $list['dynload'] = true;
        $list['nologin'] = true;
        $list['nosearch'] = true;
        $rootpath = $CFG->dataroot . "/" . $courseid . "/" . $path . "/";
        if (!file_exists($rootpath)) {
            mkdir($rootpath, $CFG->directorypermissions);
        }
        if ($dh = opendir($rootpath)) {
            while (($file = readdir($dh)) != false) {
                if ($file != '.' and $file != '..') {
                    if (filetype($rootpath . $file) == 'file') {
                        $list['list'][] = array('title' => $file, 'source' => $path . '/' . $file,
                        'size' => filesize($rootpath . $file), 'date' => filemtime($rootpath . $file),
                        'thumbnail' => $OUTPUT->pix_url(file_extension_icon($rootpath . $file, 32))->out(false));
                    } else {
                        if (!empty($path)) {
                            $currentpath = $path . '/' . $file;
                        } else {
                            $currentpath = $file;
                        }
                        $list['list'][] = array('title' => $file, 'children' => array(),
                        'thumbnail' => $OUTPUT->pix_url('f/folder-32')->out(false), 'path' => $currentpath);
                    }
                }
            }
        }
        $list['list'] = array_filter($list['list'], array($this, 'filter'));
        return $list;
    }

    /*
    * Gets a link to the spcified file.
    * @param string $info The file
    * @return The link
    */
    public function get_link($info) {
        global $CFG;
        $course = $this->get_course();
        if (strpos($info, "/") != 0) {
            $info = "/" . $info;
        }
        if ($CFG->slasharguments) {
            return file_encode_url($CFG->wwwroot, "/repository/coursefilearea/file.php/" . $course->id . $info);
        } else {
            return $CFG->wwwroot . "/repository/coursefilearea/file.php?file=/" . $this->check_url($course->id . $info);
        }
    }

    /*
    * Encodes the URL to ensure it doesn't get mangled
    * @param string $u The URL to encode
    * @return The encoded URL
    */
    public function check_url($u) {
        if (!strpos($u, "/")) {
            return rawurlencode($u);
        }
        $all = explode("/", $u);
        $f = "";
        foreach ($all as $e) {
            $f = $f . rawurlencode($e) . "/";
        }
        return substr($f, 0, strlen($f) - 1);
    }

    /*
     * Course files area only uses external links, all files need to be live
     *
     * @return int
     */
    public function supported_returntypes() {
        if (get_config('coursefilearea', 'allowinternal')) {
            return (FILE_INTERNAL | FILE_EXTERNAL);
        } else {
            return FILE_EXTERNAL;
        }
    }

    /*
     * Links or copies the file to the main file store
     *
     * @param string $url the url of file
     * @param string $filename save location
     * @return string the location of the file
     * @see curl package
     */
    public function get_file($url, $filename = '') {
        global $CFG;
        $course = $this->get_course();
        $link = $this->prepare_file($filename);
        if (strpos($url, "/") > 0) {
            $url = "/" . $url;
        }
        $target = '';
        $target = $CFG->dataroot . '/' . $course->id . "/" . $url;
        copy($target, $link);

        return array('path' => $link, 'url' => "coursefilearea;" . $target);
    }
}
