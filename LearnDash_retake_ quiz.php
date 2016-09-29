<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @package LearnDash Retake Quiz
 * @version 1.0.1
 */
/*
/*
Plugin Name: LearnDash Retake Quiz
Plugin URI: http://wooninjas.com
Description: Enables to Retake only the wrong questions of a quiz
Version: 1.0.0
Author: wooninjas
Author URI: http://wooninjas.com
*/

if(!class_exists('Learndash_RetakeQuiz')) {
    
    class Learndash_RetakeQuiz {
        private $required_plugins = array('sfwd-lms/sfwd_lms.php');

        public  function __construct() {
            if (!$this->have_required_plugins()){
                return;
            }
            add_action('learndash_quiz_completed', array($this,'ld_retake_quiz_complete'), 10, 1);
            add_filter('query', array($this,'ld_retake_modify_quiz_query'), 10, 1);
            add_action( 'wp_enqueue_scripts', array($this, 'ld_add_js'));
        }

        function ld_add_js() {
            if(is_admin()){
                return false;
            }
            wp_enqueue_script( 'ls-retake-quiz', plugins_url('js/ls_retake_quiz.js', __FILE__), array('jquery') );
        }

        function have_required_plugins() {
            if (empty($this->required_plugins))
                return true;

            $active_plugins = (array) get_option('active_plugins', array());
            
            if (is_multisite()) {
                $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
            }

            foreach ($this->required_plugins as $key => $required) {
                $required = "sfwd-lms/sfwd_lms.php";
                if (!in_array($required, $active_plugins) && !array_key_exists($required, $active_plugins))
                    return false;
            }
            return true;
        }


        /**
        * Overrides fetchAll() method in WpProQuiz_Model_QuestionMapper
        */
        function ld_retake_modify_quiz_query($sql) {
            global $wpdb;

            // If On Quiz and fetching Questions
            if(strstr($sql, $wpdb->prefix.'wp_pro_quiz_question', true) !== false 
                && strstr($sql, 'q.*', true )) {
                
                // Break quiz query to find the Quiz ID
                $quiz_query = explode("quiz_id =",$sql);
                $quiz_query = explode(' ', trim($quiz_query[1]));

                if(!$quiz_query[0]) {
                    return $sql;
                }

                if (empty($user_id)) {
                    $current_user = wp_get_current_user();

                    if (empty($current_user->ID)) {
                        return $sql;
                    }

                    $user_id = $current_user->ID;
                }
                
                $wrong_answers = get_user_meta($user_id, '_ld_quiz_retake_wrong_q');
                
                if($wrong_answers && isset($wrong_answers[0])) {
                    $wrong_answers = $wrong_answers[0];
                } else {
                    return $sql;
                }

                $quiz_id =  intval($quiz_query[0]); // Extracted Quiz ID
                $sql_parts = explode("ORDER BY", $sql);
                $wrong_question_ids = $wrong_answers[$quiz_id]["question_ids"];

                if(isset($wrong_question_ids) && $wrong_question_ids) {
                    $sql_parts[0] .= " AND q.id in (" . $wrong_question_ids .  ") ";
                }

                $sql = $sql_parts[0] . "ORDER BY ". $sql_parts[1];
            }

            return $sql;
        }

        /**
        * triggers on `wp_pro_quiz_completed_quiz` action when the quiz completes
        */

        function ld_retake_quiz_complete()
        {
            $results =  $_POST['results'];
            $wrong_ques = array();

            foreach($results as $q_id => $res) {
                if(!$res['correct'] && $q_id != "comp")
                    $wrong_ques[] = intval($q_id);
            }

            $question_ids = (count($wrong_ques) >= 1 ? implode(',',$wrong_ques) : $q_id);
            
            if (empty( $user_id)) {
                $current_user = wp_get_current_user();

                if (empty($current_user->ID)) {
                    return null;
                }

                $user_id = $current_user->ID;
            }

            //Previous Vals
            $w_qs = get_user_meta($user_id, '_ld_quiz_retake_wrong_q', false)[0];

            // All correct Ans
            if($question_ids == "comp") {
                $question_ids = 0;
            }

            $w_qs[intval($_POST['quizId'])] = [ 'question_ids' => $question_ids ];
            update_user_meta($user_id, '_ld_quiz_retake_wrong_q', $w_qs);
        }
    }
}

$Learndash_RetakeQuiz = new Learndash_RetakeQuiz;
