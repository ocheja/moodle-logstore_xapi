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

namespace MXTranslator\Events;

defined('MOODLE_INTERNAL') || die();

class RoleAssigned extends CourseViewed {
    /**
     * Reads data for an event.
     * @param [String => Mixed] $opts
     * @return [String => Mixed]
     * @override CourseViewed
     */
    public function read(array $opts) {
        return [array_merge(parent::read($opts)[0], [
            'recipe' => 'role_assigned',
            'user_id' => $opts['user']->id,
            'user_url' => $opts['user']->url,
            'user_name' => $opts['user']->fullname,
            'instructor_id' => $opts['relateduser']->id,
            'instructor_url' => $opts['relateduser']->url,
            'instructor_name' => $opts['relateduser']->fullname,
        ])];
    }
}
