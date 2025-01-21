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
 * Defines the editing form for the mathlive question type.
 *
 * @package   qtype_mathlive
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once $CFG->dirroot . '/question/type/edit_question_form.php';
class qtype_mathlive_edit_form extends question_edit_form
{

    protected function definition_inner($mform)
    {
        $menu = [
            get_string('caseno', 'qtype_mathlive'),
            get_string('caseyes', 'qtype_mathlive'),
        ];
        $mform->addElement('select', 'usecase',
            get_string('casesensitive', 'qtype_mathlive'), $menu);
        $mform->setDefault('usecase', $this->get_default_value('usecase', $menu[0]));

        $mform->addElement('static', 'answersinstruct',
            get_string('correctanswers', 'qtype_mathlive'),
            get_string('filloutoneanswer', 'qtype_mathlive'));
        $mform->closeHeaderBefore('answersinstruct');

        $this->add_per_answer_fields($mform, get_string('answerno', 'qtype_mathlive', '{no}'),
            question_bank::fraction_options(), 1, 0);

        $this->add_interactive_settings();
    }

    protected function get_more_choices_string()
    {
        return get_string('addmoreanswerblanks', 'qtype_mathlive');
    }

    protected function data_preprocessing($question)
    {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question);
        $question = $this->data_preprocessing_hints($question);

        return $question;
    }

    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);
        $answers = $data['answer'];
        $answercount = 0;
        $maxgrade = false;

        foreach ($answers as $key => $answer) {
            $trimmedanswer = trim($answer);
            if ($trimmedanswer !== '') {
                $answercount++;
                if ($data['fraction'][$key] == 1) {
                    $maxgrade = true;
                }
            } else if ($data['fraction'][$key] != 0 ||
                !html_is_blank($data['feedback'][$key]['text'])) {
                $errors["answeroptions[{$key}]"] = get_string('answermustbegiven', 'qtype_mathlive');
                $answercount++;
            }
        }
        if ($answercount == 0) {
            $errors['answeroptions[0]'] = get_string('notenoughanswers', 'qtype_mathlive', 1);
        }

        if ($maxgrade == false) {
            $errors['answeroptions[0]'] = get_string('fractionsnomax', 'question');
        }
        return $errors;
    }

    public function qtype()
    {
        return 'mathlive';
    }

    /**
     * Add a set of form fields, obtained from get_per_answer_fields, to the form,
     * one for each existing answer, with some blanks for some new ones.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $minoptions the minimum number of answer blanks to display.
     *      Default QUESTION_NUMANS_START.
     * @param $addoptions the number of answer blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_answer_fields(&$mform, $label, $gradeoptions,
        $minoptions = QUESTION_NUMANS_START, $addoptions = QUESTION_NUMANS_ADD) {
        $mform->addElement('header', 'answerhdr',
            get_string('answers', 'question'), '');
        $mform->setExpanded('answerhdr', 1);
        $answersoption = '';
        $repeatedoptions = array();
        $repeated = $this->get_per_answer_fields($mform, $label, $gradeoptions,
            $repeatedoptions, $answersoption);

        if (isset($this->question->options)) {
            $repeatsatstart = count($this->question->options->$answersoption);
        } else {
            $repeatsatstart = $minoptions;
        }

        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions,
            'noanswers', 'addanswers', $addoptions,
            $this->get_more_choices_string(), true);
    }

    /**
     * Get the list of form elements to repeat, one for each answer.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $repeatedoptions reference to array of repeated options to fill
     * @param $answersoption reference to return the name of $question->options
     *      field holding an array of answers
     * @return array of form fields.
     */
    protected function get_per_answer_fields($mform, $label, $gradeoptions,
        &$repeatedoptions, &$answersoption) {

        $repeated = array();
        $answeroptions = array();

        // $answeroptions[] = $mform->createElement('text', 'answer',$label, array('size' => 40));

        $answeroptions[] = $mform->createElement('hidden', 'answer', '', array('id' => 'id_answer'));
        $mform->setType('answer', PARAM_RAW);
        $answeroptions[] = $mform->createElement('html', '<script type="text/javascript" src="https://unpkg.com/mathlive"></script>
            <math-field id="answer_mathfield" style="width:70%;"></math-field>
            <script>
                // On page load, set the value of the hidden field to math-field
                window.addEventListener("DOMContentLoaded", function() {
                    var hiddenAnswer = document.getElementById("id_answer").value;
                    var mathField = document.getElementById("answer_mathfield");

                    if (hiddenAnswer) {
                        // If the hidden input has a value, assign it to math-field
                        mathField.setValue(hiddenAnswer);
                    }

                    // Listen to the input event of math-field and set its value to the hidden input
                    mathField.addEventListener("input", function() {
                        document.getElementById("id_answer").value = mathField.getValue("latex");
                    });
                });
            </script>
        ');

        $answeroptions[] = $mform->createElement('select', 'fraction', get_string('gradenoun'), $gradeoptions);
        $repeated[] = $mform->createElement('group', 'answeroptions',
            $label, $answeroptions, null, false);
        $repeated[] = $mform->createElement('editor', 'feedback',
            get_string('feedback', 'question'), array('rows' => 5), $this->editoroptions);
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';
        return $repeated;
    }

    /**
     * Method to add a repeating group of elements to a form.
     *
     * @param array $elementobjs Array of elements or groups of elements that are to be repeated
     * @param int $repeats no of times to repeat elements initially
     * @param array $options a nested array. The first array key is the element name.
     *    the second array key is the type of option to set, and depend on that option,
     *    the value takes different forms.
     *         'default'    - default value to set. Can include '{no}' which is replaced by the repeat number.
     *         'type'       - PARAM_* type.
     *         'helpbutton' - array containing the helpbutton params.
     *         'disabledif' - array containing the disabledIf() arguments after the element name.
     *         'rule'       - array containing the addRule arguments after the element name.
     *         'expanded'   - whether this section of the form should be expanded by default. (Name be a header element.)
     *         'advanced'   - whether this element is hidden by 'Show more ...'.
     * @param string $repeathiddenname name for hidden element storing no of repeats in this form
     * @param string $addfieldsname name for button to add more fields
     * @param int $addfieldsno how many fields to add at a time
     * @param string $addstring name of button, {no} is replaced by no of blanks that will be added.
     * @param bool $addbuttoninside if true, don't call closeHeaderBefore($addfieldsname). Default false.
     * @param string $deletebuttonname if specified, treats the no-submit button with this name as a "delete element" button
     *         in each of the elements
     * @return int no of repeats of element in this page
     */
    public function repeat_elements($elementobjs, $repeats, $options, $repeathiddenname,
        $addfieldsname, $addfieldsno = 5, $addstring = null, $addbuttoninside = false,
        $deletebuttonname = '') {
        if ($addstring === null) {
            $addstring = get_string('addfields', 'form', $addfieldsno);
        } else {
            $addstring = str_ireplace('{no}', $addfieldsno, $addstring);
        }
        $repeats = $this->optional_param($repeathiddenname, $repeats, PARAM_INT);
        $addfields = $this->optional_param($addfieldsname, '', PARAM_TEXT);
        $oldrepeats = $repeats;
        if (!empty($addfields)) {
            $repeats += $addfieldsno;
        }
        $mform = &$this->_form;
        $mform->registerNoSubmitButton($addfieldsname);
        $mform->addElement('hidden', $repeathiddenname, $repeats);
        $mform->setType($repeathiddenname, PARAM_INT);
        //value not to be overridden by submitted value
        $mform->setConstants(array($repeathiddenname => $repeats));
        $namecloned = array();
        $no = 1;
        for ($i = 0; $i < $repeats; $i++) {
            if ($deletebuttonname) {
                $mform->registerNoSubmitButton($deletebuttonname . "[$i]");
                $isdeleted = $this->optional_param($deletebuttonname . "[$i]", false, PARAM_RAW) ||
                $this->optional_param($deletebuttonname . "-hidden[$i]", false, PARAM_RAW);
                if ($isdeleted) {
                    $mform->addElement('hidden', $deletebuttonname . "-hidden[$i]", 1);
                    $mform->setType($deletebuttonname . "-hidden[$i]", PARAM_INT);
                    continue;
                }
            }
            foreach ($elementobjs as $elementobj) {
                $elementclone = fullclone($elementobj);
                $this->repeat_elements_fix_clone($i, $elementclone, $namecloned);

                if ($elementclone instanceof HTML_QuickForm_group && !$elementclone->_appendName) {
                    foreach ($elementclone->getElements() as $el) {
                        $this->repeat_elements_fix_clone($i, $el, $namecloned);
                    }
                    $elementclone->setLabel(str_replace('{no}', $no, $elementclone->getLabel()));
                } else if ($elementobj instanceof \HTML_QuickForm_submit  && $elementobj->getName() == $deletebuttonname) {
                    // Mark the "Delete" button as no-submit.
                    $onclick = $elementclone->getAttribute('onclick');
                    $skip = 'skipClientValidation = true;';
                    $onclick = ($onclick !== null) ? $skip . ' ' . $onclick : $skip;
                    $elementclone->updateAttributes(['data-skip-validation' => 1, 'data-no-submit' => 1, 'onclick' => $onclick]);
                }

                // Mark newly created elements, so they know not to look for any submitted data.
                if ($i >= $oldrepeats) {
                    $mform->note_new_repeat($elementclone->getName());
                }

                $mform->addElement($elementclone);
                $no++;
            }
        }
        for ($i = 0; $i < $repeats; $i++) {
            foreach ($options as $elementname => $elementoptions) {
                $pos = strpos($elementname, '[');
                if ($pos !== false) {
                    $realelementname = substr($elementname, 0, $pos) . "[$i]";
                    $realelementname .= substr($elementname, $pos);
                } else {
                    $realelementname = $elementname . "[$i]";
                }
                foreach ($elementoptions as $option => $params) {

                    switch ($option) {
                        case 'default':
                            $mform->setDefault($realelementname, str_replace('{no}', $i + 1, $params));
                            break;
                        case 'helpbutton':
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addHelpButton'), $params);
                            break;
                        case 'disabledif':
                        case 'hideif':
                            $pos = strpos($params[0], '[');
                            $ending = '';
                            if ($pos !== false) {
                                $ending = substr($params[0], $pos);
                                $params[0] = substr($params[0], 0, $pos);
                            }
                            foreach ($namecloned as $num => $name) {
                                if ($params[0] == $name) {
                                    $params[0] = $params[0] . "[$i]" . $ending;
                                    break;
                                }
                            }
                            $params = array_merge(array($realelementname), $params);
                            $function = ($option === 'disabledif') ? 'disabledIf' : 'hideIf';
                            call_user_func_array(array(&$mform, $function), $params);
                            break;
                        case 'rule':
                            if (is_string($params)) {
                                $params = array(null, $params, null, 'client');
                            }
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addRule'), $params);
                            break;

                        case 'type':
                            $mform->setType($realelementname, $params);
                            break;

                        case 'expanded':
                            $mform->setExpanded($realelementname, $params);
                            break;

                        case 'advanced':
                            $mform->setAdvanced($realelementname, $params);
                            break;
                    }
                }
            }
        }
        
        if($addfieldsname !== "addanswers"){
            $mform->addElement('submit', $addfieldsname, $addstring, [], false);
        }

        if (!$addbuttoninside) {
            $mform->closeHeaderBefore($addfieldsname);
        }

        return $repeats;
    }
}
