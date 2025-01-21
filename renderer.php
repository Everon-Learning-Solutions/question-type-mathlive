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
 * Mathlive question renderer class.
 *
 * @package   qtype_mathlive
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_mathlive_renderer extends qtype_renderer
{

    public function formulation_and_controls(question_attempt $qa,
        question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'hidden',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'class' => 'form-control d-inline',
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $feedbackimg = '';
        if ($options->correctness) {
            $answer = $question->get_matching_answer(array('answer' => $currentanswer));
            if ($answer) {
                $fraction = $answer->fraction;
            } else {
                $fraction = 0;
            }
            $inputattributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }

        $questiontext = $question->format_questiontext($qa);
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }
        $mathfield = '<script type="text/javascript" src="https://unpkg.com/mathlive"></script>
            <math-field id="answer_mathfield" style="width:100%;"></math-field>
            <script>
                // When the page loads, check if the hidden input has a value and assign it to math-field
                window.addEventListener("DOMContentLoaded", function() {
                    var hiddenInput = document.getElementById("' . $inputname . '");
                    var mathField = document.getElementById("answer_mathfield");

                    // If the hidden input has a value, set it as the initial value of math-field
                    if (hiddenInput.value) {
                        mathField.setValue(hiddenInput.value);
                    }

                    // Listen to the input event of math-field and update its value to the hidden input
                    mathField.addEventListener("input", function() {
                        hiddenInput.value = mathField.getValue("latex");
                    });
                });
            </script>';
        $input = $mathfield . html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

        if ($placeholder) {
            $inputinplace = html_writer::tag('label', $options->add_question_identifier_to_label(get_string('answer')),
                array('for' => $inputattributes['id'], 'class' => 'sr-only'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock form-inline'));
            $answerspan = html_writer::tag('span', $input, array('class' => 'answer'));
            $label = $options->add_question_identifier_to_label(get_string('answercolon', 'qtype_numerical'), true);
            $result .= html_writer::tag('label', $label . $answerspan,
                array('for' => $inputattributes['id'], 'style' => 'width:100%'));
            $result .= html_writer::end_tag('div');
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error(array('answer' => $currentanswer)),
                array('class' => 'validationerror'));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa)
    {
        $question = $qa->get_question();

        $answer = $question->get_matching_answer(array('answer' => $qa->get_last_qt_var('answer')));
        if (!$answer || !$answer->feedback) {
            return '';
        }

        return $question->format_text($answer->feedback, $answer->feedbackformat,
            $qa, 'question', 'answerfeedback', $answer->id);
    }

    public function correct_response(question_attempt $qa)
    {
        $question = $qa->get_question();

        $answer = $question->get_matching_answer($question->get_correct_response());
        if (!$answer) {
            return '';
        }


        $latex_answer = s($question->clean_response($answer->answer));

        return get_string('correctansweris', 'qtype_shortanswer',
            '<span id="correct_answer">\\(' . $latex_answer . '\\)</span>') . '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // 使用 MathLive 渲染包含 LaTeX 表达式的元素
                    var correctAnswerElement = document.getElementById("correct_answer");

                    if (correctAnswerElement) {
                        MathLive.renderMathInElement(correctAnswerElement);
                    }
                });
            </script>';
    }
}
