<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller
{

    /**
     * Migration of news articles from old mysql database, supplied by Dean @ Fishinabottle into a wordpress format.
     *
     * Data for news articles in the old database are held in multiple tables, e.g.
     *
     * - Fetch data from the Content table:
     *  "SELECT * FROM `content` WHERE `title` LIKE '%Japan Webpage Contest for Schools%' ORDER BY `content`.`version` DESC"
     *
     *  There is then a root_id column which links to the content_id column on content_blocks table describing the articles
     *  e.g. the root id for the latest version of the page above is 10522
     *
     * - Fetch all the data for this page (the tag column describes the type of data: heading, display_date)
     *  SELECT * FROM `content_blocks` WHERE `content_id` = 10522
     *
     * - rather than each article data being stored in columns, they are stored in separate rows with corresponding tags,
     * e.g. one row contains the heading
     *      one row contains the content html
     *      one row contains the display date
     *
     * - We'll need to loop through picking up all the rows then processing them into one insert statement for each article found.
     *
     * - This then needs to be inserted as a wordpress post with associated tags saying it's a news article?
     */
    function news_articles()
    {
        echo "<ul>";
        echo "<li>Starting news articles data migration</li>";

        $this->load->model('data_model');

        $content = $this->data_model->get_news();

        //log_message("debug", $this->db->last_query());

        log_message("debug", "Found " . $content->num_rows() . " Rows ");

        // the main record we need the latest version only
        foreach ($content->result() as $row) {

            //echo "<PRE>", print_r($row);

            $content_id = $row->id;
            $title = $row->title;
            $version = $row->version;

            echo "<li>...latest version of '" . $title . "' is " . $version . "</li>";

            //Fetch heading
            $heading = $this->data_model->get_content_blocks($content_id, "heading");

            //there might not always be one as the content in this table is mixed. If there's no matching heading, skip
            if ($heading->num_rows() > 0) {
                $heading_row = $heading->result();
                $post_title = $heading_row[0]->value;
            }

            $heading->free_result();

            //Display date
            $display_date = $this->data_model->get_content_blocks($content_id, "display_date");

            if ($display_date->num_rows() > 0) {
                $display_date_row = $display_date->result();
                $post_date = $display_date_row[0]->value;
            }
            $display_date->free_result();

            //Body HTML
            $body_content = $this->data_model->get_content_blocks($content_id, "content");

            if ($body_content->num_rows() > 0) {
                $body_content_row = $body_content->result();
                $post_content = $body_content_row[0]->value;
            }
            $body_content->free_result();

            //Create this as a wordpress post
            $this->data_model->create_wp_post($post_title, $post_content, $post_date);
        }

        echo "</ul>";

        echo "<h3>Completed import</h3>";

        //$this->load->view('welcome_message');
    }

    function events()
    {
        echo "<ul>";
        echo "<li>Starting events data migration</li>";

        $this->load->model('data_model');

        $events = $this->data_model->get_events();

        //log_message("debug", $this->db->last_query());

        log_message("debug", "Found " . $events->num_rows() . " Events ");

        // the main record we need the latest version only
        foreach ($events->result() as $row) {

            $title = $row->title;

            echo "<li>Creating event: '" . $title . "</li>";

            //Create this as a wordpress post
            $this->data_model->create_wp_event($row);

        }

        echo "</ul>";

        echo "<h3>Completed import</h3>";

        //$this->load->view('welcome_message');
    }


}
