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
 * Class for exporting user evidence with all competencies.
 *
 * @package    tool_dataprivacy
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_dataprivacy\external;
defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\external\persistent_exporter;
use core_user;
use core_user\external\user_summary_exporter;
use dml_exception;
use renderer_base;
use tool_dataprivacy\api;
use tool_dataprivacy\data_request;

/**
 * Class for exporting user evidence with all competencies.
 *
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_request_exporter extends persistent_exporter {

    /**
     * Class definition.
     *
     * @return string
     */
    protected static function define_class() {
        return data_request::class;
    }

    /**
     * Related objects definition.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'context' => 'context',
        ];
    }

    /**
     * Other properties definition.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'foruser' => [
                'type' => user_summary_exporter::read_properties_definition(),
            ],
            'requestedbyuser' => [
                'type' => user_summary_exporter::read_properties_definition(),
                'optional' => true
            ],
            'dpouser' => [
                'type' => user_summary_exporter::read_properties_definition(),
                'optional' => true
            ],
            'messagehtml' => [
                'type' => PARAM_RAW,
                'optional' => true
            ],
            'typename' => [
                'type' => PARAM_TEXT,
            ],
            'typenameshort' => [
                'type' => PARAM_TEXT,
            ],
            'statuslabel' => [
                'type' => PARAM_TEXT,
            ],
            'statuslabelclass' => [
                'type' => PARAM_TEXT,
            ],
            'canreview' => [
                'type' => PARAM_BOOL,
                'optional' => true,
                'default' => false
            ],
        ];
    }

    /**
     * Assign values to the defined other properties.
     *
     * @param renderer_base $output The output renderer object.
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_other_values(renderer_base $output) {
        $values = [];

        $foruserid = $this->persistent->get('userid');
        $user = core_user::get_user($foruserid, '*', MUST_EXIST);
        $userexporter = new user_summary_exporter($user);
        $values['foruser'] = $userexporter->export($output);

        $requestedbyid = $this->persistent->get('requestedby');
        if ($requestedbyid != $foruserid) {
            $user = core_user::get_user($requestedbyid, '*', MUST_EXIST);
            $userexporter = new user_summary_exporter($user);
            $values['requestedbyuser'] = $userexporter->export($output);
        }

        if (!empty($this->persistent->get('dpo'))) {
            $dpoid = $this->persistent->get('dpo');
            $user = core_user::get_user($dpoid, '*', MUST_EXIST);
            $userexporter = new user_summary_exporter($user);
            $values['dpouser'] = $userexporter->export($output);
        }

        $values['messagehtml'] = text_to_html($this->persistent->get('comments'));

        if ($this->persistent->get('type') == api::DATAREQUEST_TYPE_EXPORT) {
            $values['typename'] = get_string('requesttypeexport', 'tool_dataprivacy');
            $values['typenameshort'] = get_string('requesttypeexportshort', 'tool_dataprivacy');
        } else if ($this->persistent->get('type') == api::DATAREQUEST_TYPE_DELETE) {
            $values['typename'] = get_string('requesttypedelete', 'tool_dataprivacy');
            $values['typenameshort'] = get_string('requesttypedeleteshort', 'tool_dataprivacy');
        } else {
            $values['typename'] = get_string('requesttypeothers', 'tool_dataprivacy');
            $values['typenameshort'] = get_string('requesttypeothersshort', 'tool_dataprivacy');
        }

        $values['canreview'] = false;
        switch ($this->persistent->get('status')) {
            case api::DATAREQUEST_STATUS_PENDING:
                $values['statuslabelclass'] = 'label-default';
                $values['statuslabel'] = get_string('statuspending', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_PREPROCESSING:
                $values['statuslabelclass'] = 'label-default';
                $values['statuslabel'] = get_string('statuspreprocessing', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_AWAITING_APPROVAL:
                $values['statuslabelclass'] = 'label-info';
                $values['statuslabel'] = get_string('statusawaitingapproval', 'tool_dataprivacy');
                // DPO can review the request once it's ready.
                $values['canreview'] = true;
                break;
            case api::DATAREQUEST_STATUS_APPROVED:
                $values['statuslabelclass'] = 'label-info';
                $values['statuslabel'] = get_string('statusapproved', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_PROCESSING:
                $values['statuslabelclass'] = 'label-info';
                $values['statuslabel'] = get_string('statusprocessing', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_COMPLETE:
                $values['statuslabelclass'] = 'label-success';
                $values['statuslabel'] = get_string('statuscomplete', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_CANCELLED:
                $values['statuslabelclass'] = 'label-warning';
                $values['statuslabel'] = get_string('statuscancelled', 'tool_dataprivacy');
                break;
            case api::DATAREQUEST_STATUS_REJECTED:
                $values['statuslabelclass'] = 'label-important';
                $values['statuslabel'] = get_string('statusrejected', 'tool_dataprivacy');
                break;
        }

        return $values;
    }
}