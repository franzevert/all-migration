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

    /**************************************************
     *
     * Grab content from navigation table
     * select id, title, root_id, url, published, created from navigation where parent_id = 39 and visible=1 and expired IS NULL and deleted IS NULL and url !="" and root_id = id and published ORDER BY `id` ASC
     *
     * use the id (or root_id) from these records to go to the content and content_blocks tables (navigation.root_id = content.navigation_id)
     * then use content.id = content_blocks.content_id
     *
     *  So if the root_id in first query is 40...
     *
     *  select * from content c, content_blocks cb
     *  where c.id = cb.content_id
     *  and c.id = 40
     *
     */

    function news_articles2()
    {
        echo "<ul>";
        echo "<li>Starting news articles data migration</li>";

        $this->load->model('data_model');

        $content = $this->data_model->get_news();

        echo  "<li>Found " . $content->num_rows() . " Rows </li>";

        foreach ($content->result() as $row) {

            $content_id = $row->id;
            $title = $row->title;
            $post_date = $row->published;

            $post_name = str_replace("_", "-", substr($row->url, 15)); //remove the news/news-list/

            echo '<li>Processing data for... <a href="http://all-languages.org.uk/' . $row->url . '">'.$title.'</a></li>';

            //Fetch content for this article
            $article_heading = $this->data_model->get_content_for_id($content_id, "heading");
            $article_heading_row =  $article_heading->result();
            $article_heading = $article_heading_row[0]->value;

            //echo $this->db->last_query();
            //echo "<PRE>", print_r($article_heading_row);

            $article_content = $this->data_model->get_content_for_id($content_id, "content");
            $article_content_row =  $article_content->result();
            $article_content = $article_content_row[0]->value;

            //echo "<PRE>", print_r($article_content_row);
            //echo "<li><strong>Heading</strong>".$article_heading."</li>";
            //echo "<li><strong>Content</strong>".$article_content."</li>";
            //echo $this->db->last_query();

            //Create this as a wordpress post
            $this->data_model->create_wp_post($article_heading, $article_content, $post_date, $post_name);

        }

        echo "</ul>";

        echo "<h3>Completed news articles import</h3>";
    }



    function events()
    {
        echo "<ul>";
        echo "<li>Starting events data migration</li>";

        $this->load->model('data_model');

        $events = $this->data_model->get_events();

        //log_message("debug", $this->db->last_query());

        echo "<li>Found " . $events->num_rows() . " Events </li>";

        foreach ($events->result() as $row) {

            $title = $row->title;

            //echo "<PRE>", print_r($row);

            echo "<li>Creating event: '" . $title . "</li>";

            //Create this as a wordpress post
            $this->data_model->create_wp_event($row);

        }

        echo "</ul>";

        echo "<h3>Completed import</h3>";

        //$this->load->view('welcome_message');
    }


    function fix_event_slugs()
    {
        $this->load->model("data_model");
        $events = $this->data_model->get_unique_wp_posts();

        foreach ($events->result() as $row) {

            echo "<br>POST NAME : " . $row->post_name;

            //check for duplicates
            $duplicates = $this->data_model->fetch_duplicates($row->post_name, $row->id);

            $count =1;
            foreach ($duplicates->result() as $dup_row) {

                echo "<br>-----DUPLICATE : " . $dup_row->post_name;

                //echo "<PRE>", print_r($dup_row); exit;

                $new_post_name = $dup_row->post_name . "-" . $count;
                echo "<br>----- RENAMED TO : " . $new_post_name;

                $this->data_model->update_post_name($new_post_name, $dup_row->ID);

                $count++;
            }
        }

/*
        //see if this slug exists already
        $post_name = url_title($title, "-", TRUE);

        $WPDB->where("post_name", $post_name);
        $result = $WPDB->get("posts");

        $numfound = $result->num_rows();

        if ($numfound == 0) {
            return $post_name;
        } else {*/


    }


}
